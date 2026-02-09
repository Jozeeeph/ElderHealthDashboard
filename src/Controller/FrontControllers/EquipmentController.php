<?php

namespace App\Controller\FrontControllers;

use App\Repository\EquipementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/equipements')]
class EquipmentController extends AbstractController
{
    #[Route('/', name: 'front_equipements_index', methods: ['GET'])]
    public function index(EquipementRepository $equipementRepository): Response
    {
        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipementRepository->findAll(),
            'current_category' => null,
        ]);
    }

    #[Route('/categorie/{categorie}', name: 'front_equipements_by_category', methods: ['GET'])]
    public function byCategory(string $categorie, EquipementRepository $equipementRepository): Response
    {
        $equipements = $equipementRepository->findBy(['categorie' => $categorie]);
        
        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipements,
            'current_category' => $categorie,
        ]);
    }

    #[Route('/{id}', name: 'front_equipements_show', methods: ['GET'])]
    public function show(int $id, EquipementRepository $equipementRepository): Response
    {
        $equipement = $equipementRepository->find($id);
        
        if (!$equipement) {
            throw $this->createNotFoundException('Équipement non trouvé');
        }

        return $this->render('FrontOffice/equipement/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }
}