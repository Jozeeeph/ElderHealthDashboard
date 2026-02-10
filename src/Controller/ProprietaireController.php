<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_PROPRIETAIRE_MEDICAUX')]
final class ProprietaireController extends AbstractController
{
    #[Route('/proprietaire', name: 'app_proprietaire')]
    public function index(): Response
    {
        return $this->render('FrontOffice/proprietaire/index.html.twig');
    }

}
