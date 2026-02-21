<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MedicationSafetyService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openFdaApiUrl = 'https://api.fda.gov/drug/label.json',
        private readonly string $openFdaApiKey = '',
    ) {
    }

    /**
     * @return array{
     *     medications: array<int, string>,
     *     alerts: array<int, array{
     *         severity: string,
     *         code: string,
     *         title: string,
     *         details: string,
     *         source: string
     *     }>,
     *     hasCritical: bool
     * }
     */
    public function analyze(Consultation $consultation, string $rawMedications): array
    {
        $medications = $this->extractMedications($rawMedications);
        if ($medications === []) {
            return [
                'medications' => [],
                'alerts' => [],
                'hasCritical' => false,
            ];
        }

        $alerts = [];

        $alerts = array_merge(
            $alerts,
            $this->buildLocalInteractionAlerts($medications),
            $this->buildLocalContraindicationAlerts($consultation->getPatient(), $medications),
            $this->buildOpenFdaAlerts($medications)
        );

        $alerts = $this->deduplicateAlerts($alerts);
        $hasCritical = (bool) array_filter(
            $alerts,
            static fn (array $alert): bool => $alert['severity'] === 'critical'
        );

        return [
            'medications' => $medications,
            'alerts' => $alerts,
            'hasCritical' => $hasCritical,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractMedications(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\n,;|]+/u', $raw) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $item = trim($part);
            if ($item === '') {
                continue;
            }
            $item = preg_replace('/\s+/u', ' ', $item) ?? $item;
            $canonical = $this->canonicalName($item);
            if ($canonical !== '') {
                $result[] = $canonical;
            }
        }

        return array_values(array_unique($result));
    }

    private function canonicalName(string $name): string
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return '';
        }

        $aliases = [
            'paracetamol' => 'paracetamol',
            'acetaminophen' => 'paracetamol',
            'doliprane' => 'paracetamol',
            'dafalgan' => 'paracetamol',
            'ibuprofene' => 'ibuprofen',
            'ibuprofen' => 'ibuprofen',
            'advil' => 'ibuprofen',
            'nurofen' => 'ibuprofen',
            'aspirine' => 'aspirin',
            'aspirin' => 'aspirin',
            'cardioaspirine' => 'aspirin',
            'warfarine' => 'warfarin',
            'warfarin' => 'warfarin',
            'coumadin' => 'warfarin',
            'amoxicilline' => 'amoxicillin',
            'amoxicillin' => 'amoxicillin',
            'augmentin' => 'amoxicillin',
            'metformine' => 'metformin',
            'metformin' => 'metformin',
            'glucophage' => 'metformin',
            'enalapril' => 'enalapril',
            'lisinopril' => 'lisinopril',
            'ramipril' => 'ramipril',
            'diclofenac' => 'diclofenac',
            'voltaren' => 'diclofenac',
            'pseudoephedrine' => 'pseudoephedrine',
            'loratadine' => 'loratadine',
            'cetirizine' => 'cetirizine',
            'alprazolam' => 'alprazolam',
            'diazepam' => 'diazepam',
            'clonazepam' => 'clonazepam',
        ];

        foreach ($aliases as $alias => $canonical) {
            if (str_contains($normalized, $alias)) {
                return $canonical;
            }
        }

        return $normalized;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<int, string> $medications
     * @return array<int, array{severity:string,code:string,title:string,details:string,source:string}>
     */
    private function buildLocalInteractionAlerts(array $medications): array
    {
        $rules = [
            'aspirin|ibuprofen' => [
                'severity' => 'high',
                'title' => 'Association AINS',
                'details' => 'Risque accru d irritation digestive et de saignement.',
            ],
            'ibuprofen|warfarin' => [
                'severity' => 'critical',
                'title' => 'Interaction anticoagulant + AINS',
                'details' => 'Risque eleve de saignement. Verification medicale immediate recommandee.',
            ],
            'aspirin|warfarin' => [
                'severity' => 'critical',
                'title' => 'Double risque hemorragique',
                'details' => 'Association potentiellement dangereuse avec risque hemorragique majeur.',
            ],
            'enalapril|ibuprofen' => [
                'severity' => 'high',
                'title' => 'IEC + AINS',
                'details' => 'Peut reduire l effet antihypertenseur et augmenter le risque renal.',
            ],
            'lisinopril|ibuprofen' => [
                'severity' => 'high',
                'title' => 'IEC + AINS',
                'details' => 'Peut reduire l effet antihypertenseur et augmenter le risque renal.',
            ],
            'ramipril|ibuprofen' => [
                'severity' => 'high',
                'title' => 'IEC + AINS',
                'details' => 'Peut reduire l effet antihypertenseur et augmenter le risque renal.',
            ],
            'diazepam|alprazolam' => [
                'severity' => 'critical',
                'title' => 'Double benzodiazepine',
                'details' => 'Risque de sedation excessive, confusion et chute.',
            ],
        ];

        $alerts = [];
        for ($i = 0, $len = count($medications); $i < $len; $i++) {
            for ($j = $i + 1; $j < $len; $j++) {
                $pair = [$medications[$i], $medications[$j]];
                sort($pair, SORT_STRING);
                $key = $pair[0] . '|' . $pair[1];
                if (!isset($rules[$key])) {
                    continue;
                }
                $rule = $rules[$key];
                $alerts[] = [
                    'severity' => $rule['severity'],
                    'code' => 'LOCAL_INTERACTION_' . strtoupper(str_replace('|', '_', $key)),
                    'title' => $rule['title'],
                    'details' => $rule['details'] . ' (' . $pair[0] . ' + ' . $pair[1] . ')',
                    'source' => 'local_rules',
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param array<int, string> $medications
     * @return array<int, array{severity:string,code:string,title:string,details:string,source:string}>
     */
    private function buildLocalContraindicationAlerts(?Utilisateur $patient, array $medications): array
    {
        if (!$patient) {
            return [];
        }

        $age = $this->resolvePatientAge($patient);
        if ($age === null) {
            return [];
        }

        $alerts = [];
        $isElder = $age >= 65;
        if ($isElder) {
            $elderRiskMap = [
                'diazepam' => 'Risque de confusion et chute chez le sujet age.',
                'alprazolam' => 'Risque de sedation prolongee et chute chez le sujet age.',
                'clonazepam' => 'Risque de sedation et trouble de l equilibre chez le sujet age.',
                'diclofenac' => 'Risque renal et digestif augmente chez le sujet age.',
            ];

            foreach ($medications as $medication) {
                if (!isset($elderRiskMap[$medication])) {
                    continue;
                }
                $alerts[] = [
                    'severity' => 'high',
                    'code' => 'LOCAL_AGE65_' . strtoupper($medication),
                    'title' => 'Precaution patient age',
                    'details' => $elderRiskMap[$medication] . " (age {$age} ans, medicament: {$medication})",
                    'source' => 'local_rules',
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param array<int, string> $medications
     * @return array<int, array{severity:string,code:string,title:string,details:string,source:string}>
     */
    private function buildOpenFdaAlerts(array $medications): array
    {
        $alerts = [];

        foreach ($medications as $medication) {
            try {
                $warning = $this->fetchOpenFdaWarningForMedication($medication);
            } catch (\Throwable $e) {
                $this->logger->warning('OpenFDA medication check failed.', [
                    'medication' => $medication,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($warning === null) {
                continue;
            }

            $warning = $this->translateToFrench($warning);

            $alerts[] = [
                'severity' => 'info',
                'code' => 'OPENFDA_' . strtoupper($medication),
                'title' => 'Signal OpenFDA',
                'details' => $warning . " (medicament: {$medication})",
                'source' => 'openfda',
            ];
        }

        return $alerts;
    }

    private function fetchOpenFdaWarningForMedication(string $medication): ?string
    {
        $query = sprintf('openfda.generic_name:"%s"', $medication);
        $url = $this->openFdaApiUrl;

        $queryParams = [
            'search' => $query,
            'limit' => 1,
        ];
        if (trim($this->openFdaApiKey) !== '') {
            $queryParams['api_key'] = trim($this->openFdaApiKey);
        }

        $response = $this->httpClient->request('GET', $url, [
            'query' => $queryParams,
            'timeout' => 8,
        ]);

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $data = $response->toArray(false);
        if (!is_array($data) || !isset($data['results'][0]) || !is_array($data['results'][0])) {
            return null;
        }

        $record = $data['results'][0];
        $candidateFields = ['boxed_warning', 'contraindications', 'warnings'];
        foreach ($candidateFields as $field) {
            if (!isset($record[$field]) || !is_array($record[$field]) || !isset($record[$field][0])) {
                continue;
            }
            $text = trim((string) $record[$field][0]);
            if ($text === '') {
                continue;
            }

            $compact = preg_replace('/\s+/u', ' ', $text) ?? $text;
            if (mb_strlen($compact) > 220) {
                return mb_substr($compact, 0, 220) . '...';
            }
            return $compact;
        }

        return null;
    }

    private function translateToFrench(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => 'fr',
                    'dt' => 't',
                    'q' => $text,
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                ],
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() >= 400) {
                return $text;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                return $text;
            }

            $translated = '';
            foreach ($data[0] as $chunk) {
                if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
                    $translated .= $chunk[0];
                }
            }

            $translated = trim($translated);
            return $translated !== '' ? $translated : $text;
        } catch (\Throwable $e) {
            $this->logger->warning('OpenFDA warning translation failed.', [
                'error' => $e->getMessage(),
            ]);

            return $text;
        }
    }

    private function resolvePatientAge(Utilisateur $patient): ?int
    {
        if ($patient->getAge() !== null) {
            return (int) $patient->getAge();
        }
        $dob = $patient->getDateNaissance();
        if (!$dob) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        return (int) $today->diff(\DateTimeImmutable::createFromInterface($dob))->y;
    }

    /**
     * @param array<int, array{severity:string,code:string,title:string,details:string,source:string}> $alerts
     * @return array<int, array{severity:string,code:string,title:string,details:string,source:string}>
     */
    private function deduplicateAlerts(array $alerts): array
    {
        $seen = [];
        $result = [];
        foreach ($alerts as $alert) {
            $key = $alert['code'] . '|' . $alert['details'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $alert;
        }

        usort($result, static function (array $a, array $b): int {
            $rank = [
                'critical' => 0,
                'high' => 1,
                'medium' => 2,
                'info' => 3,
            ];
            $aRank = $rank[$a['severity']] ?? 99;
            $bRank = $rank[$b['severity']] ?? 99;
            if ($aRank === $bRank) {
                return strcmp($a['title'], $b['title']);
            }
            return $aRank <=> $bRank;
        });

        return $result;
    }
}
