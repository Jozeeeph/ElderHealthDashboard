<?php

namespace App\Controller\FrontControllers;

use App\Entity\Utilisateur;
use App\Form\PersonnelMedicalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NurseProfileController extends AbstractController
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

    #[Route('/infermier/profile', name: 'front_infermier_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        $form = $this->createForm(PersonnelMedicalType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis a jour.');
            return $this->redirectToRoute('front_infermier_profile');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('FrontOffice/infermier/_profile_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('FrontOffice/infermier/profile.html.twig', [
            'form' => $form->createView(),
            'nurseName' => $user->getPrenom(),
        ]);
    }
}
