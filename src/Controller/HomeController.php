<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Optionnel: si admin connecté → redirection directe vers gestion users
        $user = $this->getUser();

        if ($user instanceof Utilisateur && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_users_index');
        }

        // Sinon on affiche la page home normale
        return $this->render('BackOffice/user/index.html.twig', [
            'user' => $user, // optionnel: pour afficher infos dans twig
        ]);
    }
}
