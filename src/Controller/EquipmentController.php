<?php

namespace App\Controller;

use App\Entity\Equipement;
use App\Form\EquipementType;
use App\Repository\EquipementRepository;
use App\Service\TwilioSmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/equipements', name: 'equipment_')]
class EquipmentController extends AbstractController
{
    public function __construct(
        private readonly TwilioSmsService $twilioSmsService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EquipementRepository $equipementRepository): Response
    {
        $equipements = $equipementRepository->findAll();

        return $this->render('BackOffice/equipment/index.html.twig', [
            'equipements' => $equipements,
        ]);
    }

    #[Route('/equipements', name: 'list_index')]
    public function index2(EquipementRepository $equipementRepository): Response
    {
        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('images_directory'), $newFilename);
                    $equipement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du telechargement de l\'image');
                }
            }

            $this->syncStockStatusAndNotify($equipement, null);
            $entityManager->persist($equipement);
            $entityManager->flush();

            $this->addFlash('success', 'Equipement ajoute avec succes !');
            return $this->redirectToRoute('equipment_index');
        }

        return $this->render('BackOffice/equipment/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $previousStatus = $equipement->getStatut();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                if ($equipement->getImage()) {
                    $oldImage = $this->getParameter('images_directory') . '/' . $equipement->getImage();
                    if (file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('images_directory'), $newFilename);
                    $equipement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du telechargement de l\'image');
                }
            }

            $this->syncStockStatusAndNotify($equipement, $previousStatus);
            $entityManager->flush();

            $this->addFlash('success', 'Equipement modifie avec succes !');
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
        if ($this->isCsrfTokenValid('delete' . $equipement->getId(), $request->request->get('_token'))) {
            if ($equipement->getImage()) {
                $imagePath = $this->getParameter('images_directory') . '/' . $equipement->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($equipement);
            $entityManager->flush();

            $this->addFlash('success', 'Equipement supprime avec succes !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('equipment_index');
    }

    private function syncStockStatusAndNotify(Equipement $equipement, ?string $previousStatus): void
    {
        $quantity = (int) ($equipement->getQuantiteDisponible() ?? 0);
        if ($quantity <= 0) {
            $equipement->setStatut('en_rupture');
            if ($previousStatus !== 'en_rupture') {
                $this->twilioSmsService->sendStockOutAlert($equipement);
            }
        }
    }
}
