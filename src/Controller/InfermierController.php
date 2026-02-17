<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ConsultationRepository;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_PERSONNEL_MEDICAL')]
final class InfermierController extends AbstractController
{
    private function requirePersonnel(): Utilisateur
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
        if (strtoupper((string) $user->getRoleMetier()) !== 'PERSONNEL_MEDICAL') {
            throw $this->createAccessDeniedException('Acces reserve au personnel medical.');
        }

        return $user;
    }

    #[Route('/infermier', name: 'app_infermier_interface')]
    public function index(
        ConsultationRepository $consultationRepository,
        RendezVousRepository $rendezVousRepository
    ): Response
    {
        $user = $this->requirePersonnel();
        $consultationCount = $consultationRepository->count(['personnelMedical' => $user]);
        $plannedRdvCount = $rendezVousRepository->countPlannedForPersonnel($user);
        $plannedRendezVous = $rendezVousRepository->findPlannedForPersonnel($user, 5);
        $cancelledRdvCount = $rendezVousRepository->countCancelledForPersonnel($user);
        $cancelledRendezVous = $rendezVousRepository->findCancelledForPersonnel($user, 3);

        return $this->render('FrontOffice/infermier/index.html.twig', [
            'controller_name' => 'InfermierController',
            'nurseName' => $user->getPrenom(),
            'consultationCount' => $consultationCount,
            'plannedRdvCount' => $plannedRdvCount,
            'plannedRendezVous' => $plannedRendezVous,
            'cancelledRdvCount' => $cancelledRdvCount,
            'cancelledRendezVous' => $cancelledRendezVous,
        ]);
    }
}
