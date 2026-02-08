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
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, EquipementRepository $equipementRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Handle DELETE
        if ($request->isMethod('POST') && $request->request->get('action') === 'delete') {
            $id = $request->request->get('id');
            $equipement = $equipementRepository->find($id);
            
            if ($equipement && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
                $entityManager->remove($equipement);
                $entityManager->flush();
                $this->addFlash('success', 'Équipement supprimé avec succès !');
            }
            
            return $this->redirectToRoute('equipment_index');
        }
        
        // Handle UPDATE (inline editing)
        if ($request->isMethod('POST') && $request->request->get('action') === 'update') {
            $id = $request->request->get('id');
            $equipement = $equipementRepository->find($id);
            
            if ($equipement && $this->isCsrfTokenValid('edit' . $id, $request->request->get('_token'))) {
                // Update fields
                $equipement->setNom($request->request->get('nom'));
                $equipement->setCategorie($request->request->get('categorie'));
                $equipement->setPrix($request->request->get('prix'));
                $equipement->setQuantiteDisponible($request->request->get('quantiteDisponible'));
                $equipement->setStatut($request->request->get('statut'));
                
                // Handle file upload
                $imageFile = $request->files->get('image');
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
                
                $entityManager->flush();
                $this->addFlash('success', 'Équipement modifié avec succès !');
            }
            
            return $this->redirectToRoute('equipment_index');
        }
        
        // Handle ADD (inline adding)
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $request->request->get('action') === 'add') {
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

            // Redirect to same page to clear form
            return $this->redirectToRoute('equipment_index');
        }

        // Get equipment list
        $equipements = $equipementRepository->createQueryBuilder('e')
            ->select('e.id', 'e.nom', 'e.description', 'e.prix', 
                     'e.quantiteDisponible', 'e.statut', 'e.categorie', 'e.image')
            ->orderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('BackOffice/equipment/index.html.twig', [
            'equipements' => $equipements,
            'form' => $form->createView(),
            'edit_id' => $request->query->get('edit_id'),
        ]);
    }
    
    // NO OTHER ROUTES - Everything is on index page
}