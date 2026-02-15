<?php

namespace App\Service;

class AiSummaryService
{
    public function summarize(string $text): string
    {
        $text = strtolower(strip_tags($text));

        // mots médicaux importants
        $keywords = [
            'douleur',
            'poitrine',
            'fièvre',
            'respirer',
            'vertige',
            'fatigue',
            'saignement',
            'tête',
            'ventre',
            'coeur'
        ];

        $found = [];

        foreach ($keywords as $word) {
            if (str_contains($text, $word)) {
                $found[] = $word;
            }
        }

        if (!empty($found)) {
            return "Symptômes détectés : " . implode(', ', $found);
        }

        return mb_substr($text, 0, 80) . '...';
    }
}

