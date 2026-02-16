<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PreBilanAnalytiqueService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param Consultation[] $consultations
     * @return array{content: string, source: string, debugReason: ?string}
     */
    public function generate(Utilisateur $patient, array $consultations): array
    {
        $timeline = $this->buildTimeline($consultations);
        $quality = $this->analyzeDataQuality($timeline);

        $patientName = trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? ''));
        $prompt = $this->buildPrompt($patientName !== '' ? $patientName : 'Patient', $timeline, $quality['warnings']);
        $backend = strtolower(trim((string) (
            $_ENV['IA_BACKEND']
            ?? $_SERVER['IA_BACKEND']
            ?? 'huggingface'
        )));

        $apiKey = trim((string) (
            $_ENV['HF_TOKEN']
            ?? $_SERVER['HF_TOKEN']
            ?? $_ENV['HUGGINGFACE_API_KEY']
            ?? $_SERVER['HUGGINGFACE_API_KEY']
            ?? ''
        ));
        $model = trim((string) (
            $_ENV['HF_MEDICAL_MODEL']
            ?? $_SERVER['HF_MEDICAL_MODEL']
            ?? 'meta-llama/Llama-3.1-8B-Instruct'
        ));

        if ($backend === 'fastapi') {
            $aiResult = $this->callFastApi($prompt);
            if ($aiResult['content'] !== null) {
                return ['content' => $aiResult['content'], 'source' => 'fastapi', 'debugReason' => null];
            }

            return [
                'content' => $this->buildFallbackReport($timeline, $quality),
                'source' => 'fallback',
                'debugReason' => $aiResult['error'],
            ];
        }

        if ($apiKey !== '') {
            $aiResult = $this->callHuggingFaceRouter($prompt, $apiKey, $model);
            if ($aiResult['content'] !== null) {
                return ['content' => $aiResult['content'], 'source' => 'huggingface_router', 'debugReason' => null];
            }

            return [
                'content' => $this->buildFallbackReport($timeline, $quality),
                'source' => 'fallback',
                'debugReason' => $aiResult['error'],
            ];
        }

        return [
            'content' => $this->buildFallbackReport($timeline, $quality),
            'source' => 'fallback',
            'debugReason' => 'IA indisponible: definir IA_BACKEND=fastapi ou configurer HF_TOKEN.',
        ];
    }

    /**
     * @param Consultation[] $consultations
     * @return array<int, array{date: string, type: string, poids: ?float, systolique: ?int, diastolique: ?int}>
     */
    private function buildTimeline(array $consultations): array
    {
        $rows = [];
        foreach ($consultations as $consultation) {
            $rows[] = [
                'date' => $consultation->getDateConsultation()?->format('Y-m-d') ?? '',
                'type' => (string) ($consultation->getTypeConsultation() ?? ''),
                'poids' => $consultation->getPoidsKg() !== null ? (float) $consultation->getPoidsKg() : null,
                'systolique' => $consultation->getTensionSystolique(),
                'diastolique' => $consultation->getTensionDiastolique(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $rows;
    }

    /**
     * @param array<int, array{date: string, type: string, poids: ?float, systolique: ?int, diastolique: ?int}> $timeline
     * @param array<int, string> $qualityWarnings
     */
    private function buildPrompt(string $patientName, array $timeline, array $qualityWarnings): string
    {
        $lines = [];
        foreach ($timeline as $row) {
            $lines[] = sprintf(
                '- Date: %s | Type: %s | Poids(kg): %s | TA(mmHg): %s/%s',
                $row['date'] !== '' ? $row['date'] : '-',
                $row['type'] !== '' ? $row['type'] : '-',
                $row['poids'] !== null ? number_format($row['poids'], 1, '.', '') : '-',
                $row['systolique'] !== null ? (string) $row['systolique'] : '-',
                $row['diastolique'] !== null ? (string) $row['diastolique'] : '-'
            );
        }

        if ($lines === []) {
            $lines[] = '- Aucune consultation disponible';
        }

        $qualityLines = $qualityWarnings === []
            ? '- Aucune anomalie majeure de qualite des donnees detectee.'
            : $this->joinLines(array_map(static fn (string $w): string => '- ' . $w, $qualityWarnings));

        return <<<PROMPT
Tu es un assistant medical intelligent.

Analyse l historique des consultations d un patient et genere un pre-bilan analytique global.

Instructions :
- Compare les donnees dans le temps.
- Detecte les tendances (hausse, baisse, stabilite).
- Signale toute variation significative.
- Utilise un langage medical clair et professionnel.
- Donne des recommandations si necessaire.
- Ne fais pas de diagnostic definitif.
- Sois structure et synthetique.
- Si la serie contient moins de 3 consultations, garde une interpretation prudente.
- Reponds en texte brut (sans markdown, sans **, sans listes a puces).
- Respecte strictement 5 sections numerotees: 1., 2., 3., 4., 5.
- Limite chaque section a 2 phrases courtes maximum.
- Termine la reponse par une phrase de conclusion complete.

Donnees patient :

Nom : {$patientName}

Consultations :
{$this->joinLines($lines)}

Qualite des donnees (points de vigilance) :
{$qualityLines}

Format attendu :

Pre-bilan global :

1. Analyse du poids :
2. Analyse de la tension arterielle :
3. Analyse generale :
4. Recommandations :
5. Qualite des donnees :
PROMPT;
    }

    /**
     * @param array<int, string> $lines
     */
    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * @return array{content: ?string, error: ?string}
     */
    private function callHuggingFaceRouter(string $prompt, string $apiKey, string $model): array
    {
        $endpoint = trim((string) (
            $_ENV['HF_CHAT_ENDPOINT']
            ?? $_SERVER['HF_CHAT_ENDPOINT']
            ?? 'https://router.huggingface.co/v1/chat/completions'
        ));
        $provider = trim((string) (
            $_ENV['HF_PROVIDER']
            ?? $_SERVER['HF_PROVIDER']
            ?? ''
        ));

        $maxAttempts = 5;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'Tu rediges des pre-bilans medicaux synthetiques sans poser de diagnostic definitif.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => 700,
                        'temperature' => 0.2,
                        'provider' => $provider !== '' ? $provider : 'auto',
                    ],
                    'timeout' => 60,
                ]);

                $status = $response->getStatusCode();
                $data = $response->toArray(false);

                if ($status >= 400) {
                    $apiError = (string) ($data['error'] ?? $data['message'] ?? ('HTTP ' . $status));

                    if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                        sleep(2 * $attempt);
                        continue;
                    }

                    return ['content' => null, 'error' => $apiError];
                }

                if (is_array($data) && isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
                    $content = trim($data['choices'][0]['message']['content']);
                    if ($content !== '') {
                        $validationError = $this->validateAiContent($content);
                        if ($validationError !== null) {
                            return ['content' => null, 'error' => $validationError];
                        }
                        return ['content' => $content, 'error' => null];
                    }
                }

                return ['content' => null, 'error' => 'Reponse IA vide ou format inattendu.'];
            }

            return ['content' => null, 'error' => 'Aucune reponse IA apres plusieurs tentatives.'];
        } catch (\Throwable $e) {
            $this->logger->warning('Pre-bilan IA Hugging Face Router indisponible, fallback actif.', [
                'error' => $e->getMessage(),
                'model' => $model,
                'endpoint' => $endpoint,
                'provider' => $provider,
            ]);

            return ['content' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{content: ?string, error: ?string}
     */
    private function callFastApi(string $prompt): array
    {
        $endpoint = trim((string) (
            $_ENV['FASTAPI_ANALYZE_URL']
            ?? $_SERVER['FASTAPI_ANALYZE_URL']
            ?? 'http://127.0.0.1:5002/analyze'
        ));

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'text' => $prompt,
                ],
                'timeout' => 65,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($status >= 400) {
                $error = is_array($data)
                    ? (string) ($data['detail'] ?? $data['error'] ?? ('HTTP ' . $status))
                    : ('HTTP ' . $status);
                return ['content' => null, 'error' => $error];
            }

            if (is_array($data) && isset($data['generated_text']) && is_string($data['generated_text'])) {
                $content = trim($data['generated_text']);
                if ($content !== '') {
                    $validationError = $this->validateAiContent($content);
                    if ($validationError !== null) {
                        return ['content' => null, 'error' => $validationError];
                    }
                    return ['content' => $content, 'error' => null];
                }
            }

            if (is_array($data) && isset($data['generated_text']) && is_array($data['generated_text'])) {
                $first = $data['generated_text'][0]['generated_text'] ?? null;
                if (is_string($first) && trim($first) !== '') {
                    $content = trim($first);
                    $validationError = $this->validateAiContent($content);
                    if ($validationError !== null) {
                        return ['content' => null, 'error' => $validationError];
                    }
                    return ['content' => $content, 'error' => null];
                }
            }

            return ['content' => null, 'error' => 'Reponse FastAPI vide ou format inattendu.'];
        } catch (\Throwable $e) {
            $this->logger->warning('Pre-bilan IA FastAPI indisponible, fallback actif.', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            return ['content' => null, 'error' => $e->getMessage()];
        }
    }

    private function validateAiContent(string $content): ?string
    {
        $requiredSections = ['1.', '2.', '3.', '4.', '5.'];
        foreach ($requiredSections as $section) {
            if (!str_contains($content, $section)) {
                return 'Reponse IA incomplete: sections attendues 1..5 absentes.';
            }
        }

        return null;
    }

    /**
     * @param array<int, array{date: string, type: string, poids: ?float, systolique: ?int, diastolique: ?int}> $timeline
     * @param array{hasWarning: bool, warnings: array<int, string>, summary: string} $quality
     */
    private function buildFallbackReport(array $timeline, array $quality): string
    {
        $seriesCount = count($timeline);
        $isShortSeries = $seriesCount < 3;

        $poidsValues = array_values(array_filter(array_map(
            static fn (array $row): ?float => $row['poids'],
            $timeline
        ), static fn ($v): bool => $v !== null));

        $sysValues = array_values(array_filter(array_map(
            static fn (array $row): ?int => $row['systolique'],
            $timeline
        ), static fn ($v): bool => $v !== null));

        $diaValues = array_values(array_filter(array_map(
            static fn (array $row): ?int => $row['diastolique'],
            $timeline
        ), static fn ($v): bool => $v !== null));

        $poidsTxt = $this->describeTrend($poidsValues, 2.0, 'kg', $isShortSeries);
        $sysTxt = $this->describeTrend($sysValues, 10.0, 'mmHg', $isShortSeries);
        $diaTxt = $this->describeTrend($diaValues, 7.0, 'mmHg', $isShortSeries);

        $hasStrongVariation = str_contains($poidsTxt, 'variation significative')
            || str_contains($sysTxt, 'variation significative')
            || str_contains($diaTxt, 'variation significative');

        if ($seriesCount >= 3) {
            $general = 'Le suivi montre une evolution temporelle interpretable sur plusieurs consultations.';
        } elseif ($seriesCount === 2) {
            $general = 'Serie courte (2 consultations): tendance preliminaire a confirmer par des mesures supplementaires.';
        } else {
            $general = 'Donnees limitees: une seule consultation exploitable, interpretation prudente.';
        }

        if ($quality['hasWarning']) {
            $reco = 'Verifier la qualite des mesures (reprise de la tension au repos, verification unite/valeur du poids), puis confirmer la tendance sur des consultations supplementaires.';
        } elseif ($hasStrongVariation) {
            $reco = 'Renforcer la surveillance clinique (controle rapproche des constantes, reevaluation du mode de vie, et avis medical si persistance de la variation).';
        } else {
            $reco = 'Poursuivre le suivi regulier des constantes et maintenir les mesures hygieno-dietetiques.';
        }

        return "Pre-bilan global :\n\n"
            . "1. Analyse du poids : {$poidsTxt}\n"
            . "2. Analyse de la tension arterielle : Systolique: {$sysTxt} Diastolique: {$diaTxt}\n"
            . "3. Analyse generale : {$general}\n"
            . "4. Recommandations : {$reco}\n"
            . "5. Qualite des donnees : {$quality['summary']}\n\n"
            . "Note: ce pre-bilan est une aide a l interpretation et ne constitue pas un diagnostic definitif.";
    }

    /**
     * @param array<int, int|float> $values
     */
    private function describeTrend(array $values, float $significantThreshold, string $unit, bool $isShortSeries): string
    {
        if (count($values) < 2) {
            return 'donnees insuffisantes pour conclure.';
        }

        $first = (float) $values[0];
        $last = (float) $values[array_key_last($values)];
        $delta = $last - $first;
        $abs = abs($delta);
        $trend = $delta > 0 ? 'hausse' : ($delta < 0 ? 'baisse' : 'stabilite');

        if ($isShortSeries && $abs >= $significantThreshold) {
            $significance = 'variation observee a confirmer (serie courte)';
        } else {
            $significance = $abs >= $significantThreshold ? 'avec variation significative' : 'sans variation significative';
        }

        $deltaTxt = number_format($delta, 1, '.', '');

        return sprintf('%s (%s %s), %s.', $trend, $deltaTxt, $unit, $significance);
    }

    /**
     * @param array<int, array{date: string, type: string, poids: ?float, systolique: ?int, diastolique: ?int}> $timeline
     * @return array{hasWarning: bool, warnings: array<int, string>, summary: string}
     */
    private function analyzeDataQuality(array $timeline): array
    {
        $warnings = [];

        foreach ($timeline as $row) {
            $date = $row['date'] !== '' ? $row['date'] : 'date inconnue';

            if ($row['poids'] !== null && ($row['poids'] < 35.0 || $row['poids'] > 250.0)) {
                $warnings[] = "Poids atypique ($date): {$row['poids']} kg.";
            }
            if ($row['systolique'] !== null && ($row['systolique'] < 80 || $row['systolique'] > 200)) {
                $warnings[] = "TA systolique atypique ($date): {$row['systolique']} mmHg.";
            }
            if ($row['diastolique'] !== null && ($row['diastolique'] < 50 || $row['diastolique'] > 120)) {
                $warnings[] = "TA diastolique atypique ($date): {$row['diastolique']} mmHg.";
            }
            if ($row['systolique'] !== null && $row['diastolique'] !== null && $row['systolique'] <= $row['diastolique']) {
                $warnings[] = "TA incoherente ($date): systolique <= diastolique.";
            }
        }

        for ($i = 1; $i < count($timeline); $i++) {
            $prev = $timeline[$i - 1];
            $curr = $timeline[$i];

            $prevDate = $prev['date'] !== '' ? new \DateTimeImmutable($prev['date']) : null;
            $currDate = $curr['date'] !== '' ? new \DateTimeImmutable($curr['date']) : null;
            $days = ($prevDate && $currDate) ? abs((int) $currDate->diff($prevDate)->format('%a')) : null;

            if ($days !== null && $days <= 30) {
                if ($prev['poids'] !== null && $curr['poids'] !== null && abs($curr['poids'] - $prev['poids']) > 10.0) {
                    $warnings[] = "Variation rapide du poids entre {$prev['date']} et {$curr['date']} (>10 kg / 30 jours).";
                }
                if ($prev['systolique'] !== null && $curr['systolique'] !== null && abs($curr['systolique'] - $prev['systolique']) > 30) {
                    $warnings[] = "Variation rapide de la TA systolique entre {$prev['date']} et {$curr['date']} (>30 mmHg / 30 jours).";
                }
            }
        }

        $warnings = array_values(array_unique($warnings));
        $summary = $warnings === []
            ? 'Coherence globale acceptable des donnees saisies.'
            : 'Anomalies potentielles detectees: ' . implode(' ', $warnings);

        return [
            'hasWarning' => $warnings !== [],
            'warnings' => $warnings,
            'summary' => $summary,
        ];
    }
}
