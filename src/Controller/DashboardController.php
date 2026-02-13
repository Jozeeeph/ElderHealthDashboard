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

        return $this->render('BackOffice/dashboard/index.html.twig', [
            'stats' => [
                'users' => $utilisateurRepository->count([]),
                'consultations' => $consultationRepository->count([]),
                'rendezvous' => $rendezVousRepository->count([]),
                'events' => $eventRepository->count([]),
                'equipements' => $equipementRepository->count([]),
                'consultations_today' => $consultationToday,
                'rendezvous_today' => $rendezVousToday,
                'events_coming_soon' => $eventsComingSoon,
                'equipements_disponibles' => $equipmentAvailable,
                'equipements_rupture' => $equipmentOutOfStock,
            ],
            'recent_consultations' => $recentConsultations,
            'out_of_stock_equipments' => $outOfStockEquipments,
            'today' => $today,
        ]);
    }
}
