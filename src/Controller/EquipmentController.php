<?php

namespace App\Controller;

use App\Entity\Equipement;
use App\Form\EquipementType;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/equipment', name: 'equipment_')]
class EquipmentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EquipementRepository $equipementRepository): Response
    {
        // SEULEMENT l'affichage de la liste
        $equipements = $equipementRepository->findAll();
        
        return $this->render('BackOffice/equipment/index.html.twig', [
            'equipements' => $equipements,
        ]);
    }

    #[Route('/forum', name: 'list_index')]
    public function index2(EquipementRepository $equipementRepository): Response
    {
        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // SEULEMENT l'ajout
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $equipement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image');
                }
            }

            $entityManager->persist($equipement);
            $entityManager->flush();

            $this->addFlash('success', 'Équipement ajouté avec succès !');
            return $this->redirectToRoute('equipment_index');
        }

        return $this->render('BackOffice/equipment/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipement $equipement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // SEULEMENT l'édition (Symfony injecte automatiquement l'équipement via l'ID)
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                // Supprimer l'ancienne image si elle existe
                if ($equipement->getImage()) {
                    $oldImage = $this->getParameter('images_directory').'/'.$equipement->getImage();
                    if (file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
                
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $equipement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Équipement modifié avec succès !');
            return $this->redirectToRoute('equipment_index');
        }

        return $this->render('BackOffice/equipment/edit.html.twig', [
            'form' => $form->createView(),
            'equipement' => $equipement,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Equipement $equipement, EntityManagerInterface $entityManager): Response
    {
        // SEULEMENT la suppression
        if ($this->isCsrfTokenValid('delete'.$equipement->getId(), $request->request->get('_token'))) {
            // Supprimer l'image si elle existe
            if ($equipement->getImage()) {
                $imagePath = $this->getParameter('images_directory').'/'.$equipement->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($equipement);
            $entityManager->flush();

            $this->addFlash('success', 'Équipement supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('equipment_index');
    }
}