<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/appointments', name: 'appointment_')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('appointment/index.html.twig', [
            'message' => 'Gestion Rendez-vous works ⏱️',
        ]);
    }
}

