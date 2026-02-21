<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Repository\ConsultationRepository;

class ClinicalSeverityService
{
    public function __construct(
        private readonly ConsultationRepository $consultationRepository,
    ) {
    }

    /**
     * @return array{
     *   score:int,
     *   level:string,
     *   levelLabel:string,
     *   reasons:array<int,string>,
     *   dominant:string,
     *   dominantLabel:string,
     *   components:array<string,int>,
     *   vitals:array{
     *     systolique:?int,
     *     diastolique:?int,
     *     poids:?float,
     *     age:?int,
     *     poidsVariation30j:?float
     *   }
     * }
     */
    public function evaluate(Consultation $consultation): array
    {
        $score = 0;
        $reasons = [];
        $components = [
            'cardio' => 0,
            'metabolic' => 0,
            'medication' => 0,
            'frailty' => 0,
        ];

        $sys = $consultation->getTensionSystolique();
        $dia = $consultation->getTensionDiastolique();
        if ($sys !== null || $dia !== null) {
            if (($sys !== null && $sys > 180) || ($dia !== null && $dia > 110)) {
                $score += 40;
                $components['cardio'] += 40;
                $reasons[] = 'Tension arterielle tres elevee.';
            } elseif (($sys !== null && $sys >= 160) || ($dia !== null && $dia >= 100)) {
                $score += 25;
                $components['cardio'] += 25;
                $reasons[] = 'Hypertension de grade eleve.';
            } elseif (($sys !== null && $sys >= 140) || ($dia !== null && $dia >= 90)) {
                $score += 10;
                $components['cardio'] += 10;
                $reasons[] = 'Hypertension moderee.';
            }
        }

        $weightDelta = null;
        $weightPoints = $this->computeWeightVariationPoints($consultation, $weightDelta);
        if ($weightPoints > 0) {
            $score += $weightPoints;
            $components['metabolic'] += $weightPoints;
            $deltaText = $weightDelta !== null ? number_format(abs($weightDelta), 1, '.', '') . ' kg' : 'importante';
            $reasons[] = "Variation pondérale rapide ($deltaText sur 30 jours).";
        }

        $age = $this->resolveAge($consultation);
        if ($age !== null && $age >= 65) {
            $score += 10;
            $components['frailty'] += 10;
            $reasons[] = "Patient age ({$age} ans): vigilance renforcee.";
        }

        $medicationPoints = $this->computeMedicationRiskPoints($consultation, $age, $medicationReason);
        if ($medicationPoints > 0) {
            $score += $medicationPoints;
            $components['medication'] += $medicationPoints;
            $reasons[] = $medicationReason;
        }

        $score = max(0, min(100, $score));
        [$level, $levelLabel] = $this->toLevel($score);
        [$dominant, $dominantLabel] = $this->resolveDominantAxis($components);

        if ($reasons === []) {
            $reasons[] = 'Aucun signal clinique majeur detecte sur cette consultation.';
        }

        return [
            'score' => $score,
            'level' => $level,
            'levelLabel' => $levelLabel,
            'reasons' => $reasons,
            'dominant' => $dominant,
            'dominantLabel' => $dominantLabel,
            'components' => $components,
            'vitals' => [
                'systolique' => $sys,
                'diastolique' => $dia,
                'poids' => $consultation->getPoidsKg() !== null ? (float) $consultation->getPoidsKg() : null,
                'age' => $age,
                'poidsVariation30j' => $weightDelta !== null ? round($weightDelta, 2) : null,
            ],
        ];
    }

    private function computeWeightVariationPoints(Consultation $consultation, ?float &$weightDelta): int
    {
        $weightDelta = null;
        if ($consultation->getPoidsKg() === null) {
            return 0;
        }

        $previous = $this->consultationRepository->findPreviousForPatient($consultation);
        if (!$previous || $previous->getPoidsKg() === null) {
            return 0;
        }
        $currentDate = $consultation->getDateConsultation();
        $previousDate = $previous->getDateConsultation();
        if (!$currentDate || !$previousDate) {
            return 0;
        }

        $days = (int) abs((int) \DateTimeImmutable::createFromInterface($currentDate)
            ->diff(\DateTimeImmutable::createFromInterface($previousDate))
            ->format('%a'));
        if ($days > 30) {
            return 0;
        }

        $delta = (float) $consultation->getPoidsKg() - (float) $previous->getPoidsKg();
        $weightDelta = $delta;
        $abs = abs($delta);
        if ($abs > 8.0) {
            return 25;
        }
        if ($abs > 5.0) {
            return 20;
        }
        if ($abs > 3.0) {
            return 10;
        }

        return 0;
    }

    private function resolveAge(Consultation $consultation): ?int
    {
        $patient = $consultation->getPatient();
        if (!$patient) {
            return null;
        }

        if ($patient->getAge() !== null) {
            return (int) $patient->getAge();
        }

        $dob = $patient->getDateNaissance();
        if (!$dob) {
            return null;
        }

        return (int) (new \DateTimeImmutable('today'))->diff(\DateTimeImmutable::createFromInterface($dob))->y;
    }

    private function computeMedicationRiskPoints(Consultation $consultation, ?int $age, ?string &$reason): int
    {
        $reason = null;
        $meds = mb_strtolower((string) ($consultation->getPrescription()?->getMedicaments() ?? ''));
        if (trim($meds) === '') {
            return 0;
        }

        $hasAnticoagulant = $this->containsAny($meds, ['warfarin', 'coumadin']);
        $hasNsaid = $this->containsAny($meds, ['ibuprofen', 'advil', 'nurofen', 'aspirin', 'aspirine', 'diclofenac', 'voltaren']);
        if ($hasAnticoagulant && $hasNsaid) {
            $reason = 'Association medicamenteuse a risque hemorragique (anticoagulant + AINS).';
            return 20;
        }

        $hasBenzo = $this->containsAny($meds, ['diazepam', 'valium', 'alprazolam', 'clonazepam']);
        if ($hasBenzo && $age !== null && $age >= 65) {
            $reason = 'Sedation/chute possible chez patient age sous benzodiazepine.';
            return 15;
        }

        if ($hasAnticoagulant || $hasNsaid || $hasBenzo) {
            $reason = 'Traitement necessitant une surveillance rapprochee.';
            return 10;
        }

        return 0;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{string,string}
     */
    private function toLevel(int $score): array
    {
        if ($score >= 50) {
            return ['high', 'Elevee'];
        }
        if ($score >= 25) {
            return ['medium', 'Moyen'];
        }

        return ['low', 'Faible'];
    }

    /**
     * @param array<string,int> $components
     * @return array{string,string}
     */
    private function resolveDominantAxis(array $components): array
    {
        arsort($components);
        $key = (string) array_key_first($components);
        return match ($key) {
            'cardio' => ['cardio', 'Cardiovasculaire'],
            'metabolic' => ['metabolic', 'Metabolique'],
            'medication' => ['medication', 'Iatrogene/Prescription'],
            'frailty' => ['frailty', 'Fragilite geriatrique'],
            default => ['general', 'General'],
        };
    }
}
