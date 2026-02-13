<?php

namespace App\Controller\FrontControllers;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/infermier/rendezvous', name: 'front_infermier_rendezvous_')]
class RendezVousPersonnelController extends AbstractController
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

    #[Route('/', name: 'index')]
    public function index(Request $request, RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->requirePersonnel();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 2;
        $pagination = $rendezVousRepository->findForPersonnelPaginated($user, $page, $perPage);
        $rendezVousList = $pagination['items'];

        return $this->render('FrontOffice/infermier/rendezvous/index.html.twig', [
            'rendezVousList' => $rendezVousList,
            'pagination' => $pagination,
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/accept/{id}', name: 'accept')]
    public function accept(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        if ($rdv->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $rdv->setEtat('PLANIFIE');
        $em->flush();

        $this->addFlash('success', 'Rendez-vous accepte.');
        return $this->redirectToRoute('front_infermier_rendezvous_index');
    }

    #[Route('/refuse/{id}', name: 'refuse')]
    public function refuse(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        if ($rdv->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $rdv->setEtat('REFUSEE');
        $em->flush();

        $this->addFlash('success', 'Rendez-vous refuse.');
        return $this->redirectToRoute('front_infermier_rendezvous_index');
    }
}
