<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class DashboardMetricsService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * Retourne les métriques du dashboard admin sous forme de tableau.
     * @return array<string,mixed>
     */
    public function getAdminDashboardMetrics(): array
    {
        $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $tomorrowStart = $todayStart->modify('+1 day');

        $weekStart = $todayStart;
        $weekEnd = $todayStart->modify('+7 days');

        // ✅ Compteurs simples
        $consultationsToday = (int) $this->em->createQuery(
            'SELECT COUNT(c.id) FROM App\Entity\Consultation c
             WHERE c.dateConsultation >= :start AND c.dateConsultation < :end'
        )
            ->setParameter('start', $todayStart)
            ->setParameter('end', $tomorrowStart)
            ->getSingleScalarResult();

        $rendezVousToday = (int) $this->em->createQuery(
            'SELECT COUNT(r.id) FROM App\Entity\RendezVous r
             WHERE r.date >= :start AND r.date < :end'
        )
            ->setParameter('start', $todayStart)
            ->setParameter('end', $tomorrowStart)
            ->getSingleScalarResult();

        $eventsThisWeek = (int) $this->em->createQuery(
            'SELECT COUNT(e.id) FROM App\Entity\Event e
             WHERE e.dateDebut >= :start AND e.dateDebut < :end'
        )
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->getSingleScalarResult();

        $equipementsDisponibles = (int) $this->em->createQuery(
            'SELECT COUNT(eq.id) FROM App\Entity\Equipement eq
             WHERE eq.statut = :st'
        )
            ->setParameter('st', 'disponible')
            ->getSingleScalarResult();

        $equipementsEnRupture = (int) $this->em->createQuery(
            'SELECT COUNT(eq.id) FROM App\Entity\Equipement eq
             WHERE eq.statut = :st'
        )
            ->setParameter('st', 'en_rupture')
            ->getSingleScalarResult();

        $totalConsultations = (int) $this->em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Consultation c')
            ->getSingleScalarResult();

        $totalRendezVous = (int) $this->em->createQuery('SELECT COUNT(r.id) FROM App\Entity\RendezVous r')
            ->getSingleScalarResult();

        $totalEquipements = (int) $this->em->createQuery('SELECT COUNT(eq.id) FROM App\Entity\Equipement eq')
            ->getSingleScalarResult();

        $totalUsers = (int) $this->em->createQuery('SELECT COUNT(u.id) FROM App\Entity\Utilisateur u')
            ->getSingleScalarResult();

        $totalEvents = (int) $this->em->createQuery('SELECT COUNT(e.id) FROM App\Entity\Event e')
            ->getSingleScalarResult();

        // ✅ Série (7 derniers jours) - consultations par jour (SQL natif pour DATE() compatible)
        $conn = $this->em->getConnection();
        $start7 = $todayStart->modify('-6 days')->format('Y-m-d');
        $end7 = $todayStart->format('Y-m-d');

        $consultationsSeries = $conn->fetchAllAssociative(
            "SELECT DATE(date_consultation) AS d, COUNT(*) AS c
             FROM consultation
             WHERE DATE(date_consultation) BETWEEN :s AND :e
             GROUP BY DATE(date_consultation)
             ORDER BY d ASC",
            ['s' => $start7, 'e' => $end7]
        );

        $rdvSeries = $conn->fetchAllAssociative(
            "SELECT DATE(date) AS d, COUNT(*) AS c
             FROM rendez_vous
             WHERE DATE(date) BETWEEN :s AND :e
             GROUP BY DATE(date)
             ORDER BY d ASC",
            ['s' => $start7, 'e' => $end7]
        );

        return [
            'today' => [
                'consultations' => $consultationsToday,
                'rendez_vous' => $rendezVousToday,
            ],
            'week' => [
                'events' => $eventsThisWeek,
            ],
            'equipements' => [
                'disponibles' => $equipementsDisponibles,
                'en_rupture' => $equipementsEnRupture,
            ],
            'totals' => [
                'consultations' => $totalConsultations,
                'rendez_vous' => $totalRendezVous,
                'equipements' => $totalEquipements,
                'users' => $totalUsers,
                'events' => $totalEvents,
            ],
            'series_7_days' => [
                'consultations' => $consultationsSeries,
                'rendez_vous' => $rdvSeries,
            ],
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}