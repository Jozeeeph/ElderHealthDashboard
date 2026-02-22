<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class AiDashboardInsightService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function generateInsights(array $metrics): string
    {
        try {
            return $this->buildHeuristicReport($metrics);
        } catch (\Throwable $e) {
            $this->logger->error('AI local report exception', ['exception' => $e]);
            return $this->fallbackInsights($metrics, $e->getMessage());
        }
    }

    private function buildHeuristicReport(array $metrics): string
    {
        $todayConsultations = (int)($metrics['today']['consultations'] ?? 0);
        $todayRdv = (int)($metrics['today']['rendez_vous'] ?? 0);
        $weekEvents = (int)($metrics['week']['events'] ?? 0);

        $equipAvailable = (int)($metrics['equipements']['disponibles'] ?? 0);
        $equipOut = (int)($metrics['equipements']['en_rupture'] ?? 0);

        $totalUsers = (int)($metrics['totals']['users'] ?? 0);
        $totalConsultations = (int)($metrics['totals']['consultations'] ?? 0);
        $totalRdv = (int)($metrics['totals']['rendez_vous'] ?? 0);
        $totalEquipements = (int)($metrics['totals']['equipements'] ?? 0);
        $totalEvents = (int)($metrics['totals']['events'] ?? 0);

        $consultSeries = $metrics['series_7_days']['consultations'] ?? [];
        $rdvSeries = $metrics['series_7_days']['rendez_vous'] ?? [];

        $lines = [];

        $lines[] = "🧠 Rapport automatique — Dashboard Admin";
        $lines[] = "------------------------------------------";

        $lines[] = "";
        $lines[] = "📊 Vue globale :";
        $lines[] = "- Utilisateurs : $totalUsers";
        $lines[] = "- Consultations (total) : $totalConsultations";
        $lines[] = "- Rendez-vous (total) : $totalRdv";
        $lines[] = "- Événements (total) : $totalEvents";
        $lines[] = "- Équipements (total) : $totalEquipements";
        $lines[] = "- Stock : $equipAvailable disponibles, $equipOut en rupture";

        $lines[] = "";
        $lines[] = "📅 Activité du jour :";
        $lines[] = "- Consultations aujourd’hui : $todayConsultations";
        $lines[] = "- Rendez-vous aujourd’hui : $todayRdv";
        $lines[] = "- Événements semaine : $weekEvents";

        $lines[] = "";
        $lines[] = "📈 Tendances (7 jours) :";
        $lines[] = "- Consultations : " . $this->trendLabel($consultSeries);
        $lines[] = "- Rendez-vous : " . $this->trendLabel($rdvSeries);

        $lines[] = "";
        $lines[] = "🚨 Alertes :";

        $alerts = 0;

        if ($equipOut > 0) {
            $alerts++;
            $lines[] = "- ⚠️ $equipOut équipement(s) en rupture.";
        }

        $avgConsult = $this->average($consultSeries);
        if ($avgConsult > 0 && $todayConsultations > $avgConsult * 1.5) {
            $alerts++;
            $lines[] = "- 📈 Forte hausse des consultations aujourd’hui.";
        }

        if ($alerts === 0) {
            $lines[] = "- Aucun signal critique détecté.";
        }

        $lines[] = "";
        $lines[] = "✅ Rapport généré localement (sans API externe).";

        return implode("\n", $lines);
    }

    private function extractSeriesValues(array $series): array
    {
        $values = [];

        foreach ($series as $row) {
            if (is_array($row) && isset($row['c'])) {
                $values[] = (int)$row['c'];
            }
        }

        return $values;
    }

    private function trendLabel(array $series): string
    {
        $values = $this->extractSeriesValues($series);

        if (count($values) < 2) {
            return "données insuffisantes";
        }

        $first = $values[0];
        $last = $values[count($values) - 1];

        if ($last > $first) return "hausse";
        if ($last < $first) return "baisse";

        return "stable";
    }

    private function average(array $series): float
    {
        $values = $this->extractSeriesValues($series);

        if (count($values) === 0) return 0;

        return array_sum($values) / count($values);
    }

    private function fallbackInsights(array $metrics, string $reason): string
    {
        return "⚠️ Erreur génération IA : $reason";
    }
}