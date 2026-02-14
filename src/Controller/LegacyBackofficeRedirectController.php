<?php

namespace App\Controller;

use App\Repository\ConsultationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegacyBackofficeRedirectController extends AbstractController
{
    #[Route('/patient', name: 'legacy_equipment_index_noslash', methods: ['GET'])]
    #[Route('/patient/', name: 'legacy_equipment_index', methods: ['GET'])]
    public function legacyEquipmentIndex(): Response
    {
        return $this->redirectToRoute('equipment_index');
    }

    #[Route('/consultations', name: 'legacy_consultation_index_noslash', methods: ['GET'])]
    #[Route('/consultations/', name: 'legacy_consultation_index', methods: ['GET'])]
    public function consultations(Request $request, ConsultationRepository $repo): Response
    {
        $patient = trim((string) $request->query->get('patient', ''));
        $personnel = trim((string) $request->query->get('personnel', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 2;
        $dateInput = $request->query->get('date');
        $date = null;
        if (is_string($dateInput) && $dateInput !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateInput) ?: null;
            if (!$date) {
                $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateInput) ?: null;
            }
        }

        $pagination = $repo->findFilteredPaginated(
            $patient !== '' ? $patient : null,
            $personnel !== '' ? $personnel : null,
            $date,
            $page,
            $perPage
        );

        return $this->render('BackOffice/consultation/index.html.twig', [
            'consultations' => $pagination['items'],
            'pagination' => $pagination,
            'consultation_index_route' => 'legacy_consultation_index',
            'consultation_archives_route' => 'legacy_consultation_archives',
            'filters' => [
                'patient' => $patient,
                'personnel' => $personnel,
                'date' => $dateInput,
            ],
        ]);
    }

    #[Route('/consultations/archives', name: 'legacy_consultation_archives', methods: ['GET'])]
    public function consultationsArchives(Request $request, ConsultationRepository $repo): Response
    {
        $patient = trim((string) $request->query->get('patient', ''));
        $personnel = trim((string) $request->query->get('personnel', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 2;
        $dateInput = $request->query->get('date');
        $date = null;
        if (is_string($dateInput) && $dateInput !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateInput) ?: null;
            if (!$date) {
                $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateInput) ?: null;
            }
        }

        $limitDate = new \DateTimeImmutable('today -4 days');
        $pagination = $repo->findArchivedPaginated(
            $patient !== '' ? $patient : null,
            $personnel !== '' ? $personnel : null,
            $date,
            $limitDate,
            $page,
            $perPage
        );

        return $this->render('BackOffice/consultation/archives.html.twig', [
            'consultations' => $pagination['items'],
            'pagination' => $pagination,
            'consultation_index_route' => 'legacy_consultation_index',
            'consultation_archives_route' => 'legacy_consultation_archives',
            'filters' => [
                'patient' => $patient,
                'personnel' => $personnel,
                'date' => $dateInput,
            ],
            'limit_date' => $limitDate,
        ]);
    }
}
