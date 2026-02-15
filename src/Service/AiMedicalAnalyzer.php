<?php

namespace App\Service;

class AiMedicalAnalyzer
{
    private array $symptoms = [
        'urgence' => [
            'poitrine','respirer','étouffer','saignement','évanoui','inconscient','convulsion'
        ],
        'important' => [
            'vertige','fièvre','vomissement','douleur','tête','coeur','pression','fatigue'
        ],
        'léger' => [
            'toux','rhume','mal de gorge','stress','fatigué','migraine'
        ]
    ];

    public function analyze(string $text): array
    {
        $text = strtolower($text);
        $detected = [];
        $level = 'none';

        foreach ($this->symptoms as $severity => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    $detected[] = $word;
                    $level = $this->highestLevel($level, $severity);
                }
            }
        }

        return [
            'detected' => array_unique($detected),
            'level' => $level
        ];
    }

    private function highestLevel(string $current, string $new): string
    {
        $priority = ['none'=>0,'léger'=>1,'important'=>2,'urgence'=>3];

        return $priority[$new] > $priority[$current] ? $new : $current;
    }

    public function needsMedicalReply(string $text): bool
    {
        return $this->analyze($text)['level'] !== 'none';
    }

    public function generateReply(string $text): string
    {
        $analysis = $this->analyze($text);

        return match($analysis['level']) {

            'urgence' =>
                "🚨 Symptômes critiques détectés (" .
                implode(', ', $analysis['detected']) .
                "). Consultez immédiatement un service d’urgence.",

            'important' =>
                "⚠️ Symptômes détectés : " .
                implode(', ', $analysis['detected']) .
                ". Nous recommandons de consulter un professionnel de santé.",

            'léger' =>
                "ℹ️ Symptômes légers détectés : " .
                implode(', ', $analysis['detected']) .
                ". Surveillez votre état et consultez si cela persiste.",

            default =>
                "Merci pour votre message. Un professionnel vous répondra bientôt."
        };
    }
}
