<?php

namespace App\Controller;

use App\Repository\ConsultationRepository;
use App\Repository\EquipementRepository;
use App\Repository\EventRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard')]
    public function index(
        ConsultationRepository $consultationRepository,
        RendezVousRepository $rendezVousRepository,
        EventRepository $eventRepository,
        EquipementRepository $equipementRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $nextWeek = $today->modify('+7 days');

        $consultationToday = (int) $consultationRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.dateConsultation >= :today')
            ->andWhere('c.dateConsultation < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        $rendezVousToday = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.date >= :today')
            ->andWhere('r.date < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        $eventsComingSoon = (int) $eventRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.dateDebut >= :today')
            ->andWhere('e.dateDebut < :nextWeek')
            ->setParameter('today', $today)
            ->setParameter('nextWeek', $nextWeek)
            ->getQuery()
            ->getSingleScalarResult();

        $equipmentAvailable = (int) $equipementRepository->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->andWhere('eq.statut = :status')
            ->setParameter('status', 'disponible')
            ->getQuery()
            ->getSingleScalarResult();

        $equipmentOutOfStock = (int) $equipementRepository->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->andWhere('eq.statut = :status')
            ->setParameter('status', 'en_rupture')
            ->getQuery()
            ->getSingleScalarResult();

        $outOfStockEquipments = $equipementRepository->createQueryBuilder('eq')
            ->select('eq.id', 'eq.nom', 'eq.quantiteDisponible', 'eq.categorie')
            ->andWhere('eq.statut = :status')
            ->setParameter('status', 'en_rupture')
            ->orderBy('eq.nom', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $recentConsultations = $consultationRepository->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $totalConsultations = (int) $consultationRepository->count([]);
        $totalRendezVous = (int) $rendezVousRepository->count([]);
        $totalEquipements = (int) $equipementRepository->count([]);

        $consultationPercent = $totalConsultations > 0
            ? (int) round(($consultationToday / $totalConsultations) * 100)
            : 0;
        $rendezVousPercent = $totalRendezVous > 0
            ? (int) round(($rendezVousToday / $totalRendezVous) * 100)
            : 0;
        $equipementPercent = $totalEquipements > 0
            ? (int) round(($equipmentAvailable / $totalEquipements) * 100)
            : 0;

        $startDate = $today->modify('-6 days');
        $connection = $consultationRepository->getEntityManager()->getConnection();

        $consultationRows = $connection->executeQuery(
            'SELECT DATE(date_consultation) AS d, COUNT(*) AS c
             FROM consultation
             WHERE DATE(date_consultation) BETWEEN :start AND :end
             GROUP BY DATE(date_consultation)',
            [
                'start' => $startDate->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ]
        )->fetchAllAssociative();

        $rendezVousRows = $connection->executeQuery(
            'SELECT DATE(date) AS d, COUNT(*) AS c
             FROM rendez_vous
             WHERE DATE(date) BETWEEN :start AND :end
             GROUP BY DATE(date)',
            [
                'start' => $startDate->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ]
        )->fetchAllAssociative();

        $consultationByDay = [];
        foreach ($consultationRows as $row) {
            $consultationByDay[$row['d']] = (int) $row['c'];
        }

        $rendezVousByDay = [];
        foreach ($rendezVousRows as $row) {
            $rendezVousByDay[$row['d']] = (int) $row['c'];
        }

        $chartLabels = [];
        $consultationSeries = [];
        $rendezVousSeries = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $startDate->modify('+' . $i . ' days');
            $key = $day->format('Y-m-d');
            $chartLabels[] = $day->format('d/m');
            $consultationSeries[] = $consultationByDay[$key] ?? 0;
            $rendezVousSeries[] = $rendezVousByDay[$key] ?? 0;
        }

        $otherEquipements = max(0, $totalEquipements - $equipmentAvailable - $equipmentOutOfStock);

        return $this->render('BackOffice/dashboard/index.html.twig', [
            'stats' => [
                'users' => $utilisateurRepository->count([]),
                'consultations' => $totalConsultations,
                'rendezvous' => $totalRendezVous,
                'events' => $eventRepository->count([]),
                'equipements' => $totalEquipements,
                'consultations_today' => $consultationToday,
                'rendezvous_today' => $rendezVousToday,
                'events_coming_soon' => $eventsComingSoon,
                'equipements_disponibles' => $equipmentAvailable,
                'equipements_rupture' => $equipmentOutOfStock,
            ],
            'progress' => [
                'consultations' => $consultationPercent,
                'rendezvous' => $rendezVousPercent,
                'equipements' => $equipementPercent,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'consultations' => $consultationSeries,
                'rendezvous' => $rendezVousSeries,
                'equipements' => [
                    'disponible' => $equipmentAvailable,
                    'rupture' => $equipmentOutOfStock,
                    'autres' => $otherEquipements,
                ],
            ],
            'recent_consultations' => $recentConsultations,
            'out_of_stock_equipments' => $outOfStockEquipments,
            'today' => $today,
        ]);
    }
}
