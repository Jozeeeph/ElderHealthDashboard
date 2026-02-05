<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/equipment', name: 'equipment_')]
class EquipmentController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('equipment/index.html.twig', [
            'message' => 'Gestion Équipements médicaux works 🎉',
        ]);
    }
}
