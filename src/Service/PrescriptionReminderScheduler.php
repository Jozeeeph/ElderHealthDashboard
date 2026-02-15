<?php

namespace App\Service;

use App\Entity\Prescription;

class PrescriptionReminderScheduler
{
    /**
     * @return list<\DateTimeImmutable>
     */
    public function buildSlotsForDate(Prescription $prescription, \DateTimeImmutable $day): array
    {
        $dateDebut = $prescription->getDateDebut();
        $dateFin = $prescription->getDateFin();

        if (!$dateDebut || !$dateFin) {
            return [];
        }

        $dayDate = $day->setTime(0, 0, 0);
        $debutDate = \DateTimeImmutable::createFromInterface($dateDebut)->setTime(0, 0, 0);
        $finDate = \DateTimeImmutable::createFromInterface($dateFin)->setTime(0, 0, 0);

        if ($dayDate < $debutDate || $dayDate > $finDate) {
            return [];
        }

        // "frequence" is the source of reminder time.
        $times = $this->extractTimesFromFrequence((string) $prescription->getFrequence());
        if ($times === []) {
            return [];
        }

        $slots = [];
        foreach ($times as $time) {
            [$hour, $minute] = explode(':', $time);
            $slots[] = $dayDate->setTime((int) $hour, (int) $minute);
        }

        return $slots;
    }

    /**
     * Parse hours from "frequence" field.
     * Supported examples: "matin 9H", "09h", "09:30", "9h, 14h, 20h".
     *
     * @return list<string> HH:MM
     */
    public function extractTimesFromFrequence(string $frequence): array
    {
        $text = mb_strtolower(trim($frequence));
        if ($text === '') {
            return [];
        }

        $times = [];

        // Real-time keywords: immediate reminder at current minute.
        if (str_contains($text, 'maintenant') || str_contains($text, 'temps reel') || str_contains($text, 'reel')) {
            $now = new \DateTimeImmutable();
            $times[] = $now->format('H:i');
        }

        // Explicit hour patterns: 9h, 21H, 09:30, 9h15...
        preg_match_all('/\b([01]?\d|2[0-3])\s*(?:h|:)\s*([0-5]?\d)?\b/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $hour = (int) $match[1];
            $minute = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 0;
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                continue;
            }
            $times[] = sprintf('%02d:%02d', $hour, $minute);
        }

        // Keyword defaults if user writes only period words without hour.
        $keywordDefaults = [
            'matin' => '09:00',
            'midi' => '12:00',
            'apres midi' => '16:00',
            'apres-midi' => '16:00',
            'soir' => '20:00',
            'nuit' => '23:00',
        ];
        foreach ($keywordDefaults as $keyword => $defaultTime) {
            if (str_contains($text, $keyword)) {
                $times[] = $defaultTime;
            }
        }

        $times = array_values(array_unique($times));
        sort($times);

        return $times;
    }

    public function extractDailyDoseCount(string $dosage): int
    {
        if (preg_match('/\d+/', $dosage, $matches) === 1) {
            $value = (int) $matches[0];
            if ($value > 0) {
                return min($value, 8);
            }
        }

        return 1;
    }

    /**
     * @return list<string> HH:MM
     */
    public function getTimesForCount(int $count): array
    {
        $count = max(1, min(8, $count));

        $presets = [
            1 => ['12:00'],
            2 => ['09:00', '20:00'],
            3 => ['09:00', '14:00', '20:00'],
            4 => ['09:00', '12:00', '17:00', '20:00'],
            5 => ['08:00', '11:00', '14:00', '17:00', '20:00'],
            6 => ['08:00', '10:00', '12:00', '14:00', '17:00', '20:00'],
        ];

        if (isset($presets[$count])) {
            return $presets[$count];
        }

        // Fallback for 7-8 doses: spread between 08:00 and 21:00.
        $startMinutes = 8 * 60;
        $endMinutes = 21 * 60;
        $step = (int) floor(($endMinutes - $startMinutes) / max(1, ($count - 1)));

        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $minutes = min($endMinutes, $startMinutes + ($i * $step));
            $hour = intdiv($minutes, 60);
            $minute = $minutes % 60;
            $times[] = sprintf('%02d:%02d', $hour, $minute);
        }

        return $times;
    }
}
