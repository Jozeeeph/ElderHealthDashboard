<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AdminSecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si quelqu’un est déjà connecté
        if ($this->getUser()) {
            // ✅ Admin -> ok, on l'envoie au backoffice
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirect('/admin/users');
            }

            // ❌ Patient / Infermier connecté -> il ne doit pas voir la page login admin
            if ($this->isGranted('ROLE_PATIENT')) {
                return $this->redirect('/patient');
            }

            if ($this->isGranted('ROLE_PERSONNEL_MEDICAL')) {
                return $this->redirect('/infermier');
            }

            // fallback
            return $this->redirect('/');
        }

        return $this->render('security/admin_login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        // Symfony gère ça automatiquement
    }
}
