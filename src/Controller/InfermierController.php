<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_PERSONNEL_MEDICAL')]
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
