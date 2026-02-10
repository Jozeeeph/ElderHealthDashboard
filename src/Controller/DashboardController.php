<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('BackOffice/dashboard/index.html.twig');
    }

}
