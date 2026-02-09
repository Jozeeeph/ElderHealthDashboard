<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontOfficeController extends AbstractController
{
    #[Route('/', name: 'app_public_site')]
    public function index(): Response
    {
        return $this->render('FrontOffice/home/index.html.twig');
    }
}
