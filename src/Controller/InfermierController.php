<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfermierController extends AbstractController
{
    #[Route('/infermier', name: 'app_infermier_interface')]
    public function index(): Response
    {
        return $this->render('FrontOffice/infermier/index.html.twig', [
            'controller_name' => 'InfermierController',
        ]);
    }
}
