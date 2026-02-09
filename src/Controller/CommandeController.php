<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Repository\EquipementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/backoffice/commande')]
class CommandeController extends AbstractController
{
    #[Route('/', name: 'commande_index', methods: ['GET'])]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findAll();
        
        // Calculer le total pour chaque commande
        foreach ($commandes as $commande) {
            $commande->calculerMontantTotal();
        }
        
        return $this->render('backoffice/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/new', name: 'commande_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        EquipementRepository $equipementRepository,
        UserRepository $userRepository
    ): Response
    {
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande, [
            'equipements' => $equipementRepository->findAll(),
            'users' => $userRepository->findAll(),
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer le montant total
            $commande->calculerMontantTotal();
            
            $entityManager->persist($commande);
            $entityManager->flush();

            $this->addFlash('success', 'Commande créée avec succès!');

            return $this->redirectToRoute('commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backoffice/commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        // Calculer le montant total
        $commande->calculerMontantTotal();
        
        return $this->render('backoffice/commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}/edit', name: 'commande_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Commande $commande, 
        EntityManagerInterface $entityManager,
        EquipementRepository $equipementRepository,
        UserRepository $userRepository
    ): Response
    {
        $form = $this->createForm(CommandeType::class, $commande, [
            'equipements' => $equipementRepository->findAll(),
            'users' => $userRepository->findAll(),
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le montant total
            $commande->calculerMontantTotal();
            
            $entityManager->flush();

            $this->addFlash('success', 'Commande modifiée avec succès!');

            return $this->redirectToRoute('commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backoffice/commande/edit.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->request->get('_token'))) {
            $entityManager->remove($commande);
            $entityManager->flush();
            
            $this->addFlash('success', 'Commande supprimée avec succès!');
        }

        return $this->redirectToRoute('commande_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/update-statut', name: 'commande_update_statut', methods: ['POST'])]
    public function updateStatut(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        $nouveauStatut = $request->request->get('statut');
        
        if (in_array($nouveauStatut, ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'])) {
            $commande->setStatut($nouveauStatut);
            
            // Si le statut est "expédiée", définir la date d'expédition
            if ($nouveauStatut === 'expediee') {
                $commande->setDateLivraison(new \DateTime('+3 days'));
            }
            
            // Si le statut est "livrée", définir la date de livraison à aujourd'hui
            if ($nouveauStatut === 'livree') {
                $commande->setDateLivraison(new \DateTime());
            }
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Statut de la commande mis à jour!');
        } else {
            $this->addFlash('error', 'Statut invalide!');
        }

        return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
    }
}