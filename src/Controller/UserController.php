<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'user_')]
class UserController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    
    {
        return $this->render('BackOffice/user/index.html.twig', [
            'message' => 'Gestion Utilisateurs works 🎉',
        ]);
    }
}
