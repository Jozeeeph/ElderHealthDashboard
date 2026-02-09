<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // ✅ Page d'accueil publique (ou redirection)
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si admin connecté ➜ rediriger vers sa page d'accueil admin
        if ($user instanceof Utilisateur && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_home');
        }

        // Sinon, page d'accueil (publique / front)
        return $this->render('front/home.html.twig'); // adapte si tu as un autre twig
    }

    // ✅ Page d'accueil ADMIN (celle que tu veux)
    #[Route('/admin/home', name: 'admin_home')]
    public function adminHome(): Response
    {
        return $this->render('BackOffice/home/index.html.twig');
    }
}
