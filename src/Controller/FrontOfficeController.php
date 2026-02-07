<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontOfficeController extends AbstractController
{
    #[Route('/site', name: 'app_public_site')]
    public function index(): Response
    {
        return $this->render('FrontOffice/home/index.html.twig');
    }
}