<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consultations', name: 'consultation_')]
class ConsultationController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('consultation/index.html.twig', [
            'message' => 'Gestion Consultations works 🎉',
        ]);
    }
}
