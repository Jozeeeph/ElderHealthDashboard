<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Equipement;
use App\Repository\CommandeRepository;
use App\Repository\EquipementRepository;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_PROPRIETAIRE_MEDICAUX')]
final class ProprietaireController extends AbstractController
{
    #[Route('/proprietaire', name: 'front_proprietaire_redirect', methods: ['GET'])]
    public function redirectToEquipements(): Response
    {
        return $this->redirectToRoute('front_proprietaire_equipements');
    }

    #[Route('/proprietaire/equipements', name: 'front_proprietaire_equipements', methods: ['GET'])]
    public function index(
        EquipementRepository $equipementRepository,
        CommandeRepository $commandeRepository
    ): Response {
        $user = $this->getUser();

        $equipements = $equipementRepository->findBy(
            ['utilisateur' => $user],
            ['dateAjout' => 'DESC']
        );

        $commandes = $commandeRepository->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->leftJoin('c.equipements', 'e')
            ->addSelect('e')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('FrontOffice/proprietaire/proprietaire.html.twig', [
            'equipements' => $equipements,
            'commandes' => $commandes,
            'mode' => 'create',
            'selected' => null,
        ]);
    }

    #[Route('/proprietaire/equipements/new', name: 'front_proprietaire_equipements_new', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        EquipementRepository $equipementRepository,
        CommandeRepository $commandeRepository,
        SluggerInterface $slugger,
        ValidatorInterface $validator,
        ImageProcessor $imageProcessor
    ): Response {
        if (!$this->isCsrfTokenValid('equipement_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('front_proprietaire_equipements');
        }

        $user = $this->getUser();
        $equipement = new Equipement();
        $equipement->setUtilisateur($user);
        $nom = trim((string) $request->request->get('nom'));
        $equipement->setNom($nom);
        $equipement->setCategorie($request->request->get('categorie') ?: null);
        $equipement->setPrix($request->request->get('prix') ?: '0.00');
        $equipement->setQuantiteDisponible((int) $request->request->get('quantiteDisponible'));
        $equipement->setStatut($request->request->get('statut') ?: 'disponible');
        $equipement->setDescription($request->request->get('description') ?: null);

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = (string) $slugger->slug($originalFilename ?: 'equipment');

            try {
                $processedFilename = $imageProcessor->processAndStore($imageFile, $safeFilename);
                $equipement->setImage($processedFilename);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur lors du traitement AI de l\'image');
            }
        }

        $errors = $validator->validate($equipement);
        if (count($errors) > 0) {
            $equipements = $equipementRepository->findBy(
                ['utilisateur' => $user],
                ['dateAjout' => 'DESC']
            );

            $commandes = $commandeRepository->createQueryBuilder('c')
                ->select('DISTINCT c')
                ->leftJoin('c.equipements', 'e')
                ->addSelect('e')
                ->andWhere('e.utilisateur = :user')
                ->setParameter('user', $user)
                ->orderBy('c.dateCommande', 'DESC')
                ->getQuery()
                ->getResult();

            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }

            return $this->render('FrontOffice/proprietaire/proprietaire.html.twig', [
                'equipements' => $equipements,
                'commandes' => $commandes,
                'mode' => 'create',
                'selected' => $equipement,
                'form_errors' => $messages,
            ]);
        }

        $entityManager->persist($equipement);
        $entityManager->flush();

        $this->addFlash('success', 'Equipement ajoute avec succes !');
        return $this->redirectToRoute('front_proprietaire_equipements');
    }

    #[Route('/proprietaire/equipements/{id}', name: 'front_proprietaire_equipements_show', methods: ['GET'])]
    public function show(
        int $id,
        EquipementRepository $equipementRepository,
        CommandeRepository $commandeRepository
    ): Response {
        $user = $this->getUser();
        $selected = $equipementRepository->findOneBy(['id' => $id, 'utilisateur' => $user]);

        if (!$selected) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        $equipements = $equipementRepository->findBy(
            ['utilisateur' => $user],
            ['dateAjout' => 'DESC']
        );

        $commandes = $commandeRepository->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->leftJoin('c.equipements', 'e')
            ->addSelect('e')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('FrontOffice/proprietaire/proprietaire.html.twig', [
            'equipements' => $equipements,
            'commandes' => $commandes,
            'mode' => 'view',
            'selected' => $selected,
        ]);
    }

    #[Route('/proprietaire/equipements/{id}/edit', name: 'front_proprietaire_equipements_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        EquipementRepository $equipementRepository,
        CommandeRepository $commandeRepository,
        SluggerInterface $slugger,
        ValidatorInterface $validator,
        ImageProcessor $imageProcessor
    ): Response {
        $user = $this->getUser();
        $equipement = $equipementRepository->findOneBy(['id' => $id, 'utilisateur' => $user]);

        if (!$equipement) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('equipement_edit' . $equipement->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide');
                return $this->redirectToRoute('front_proprietaire_equipements_edit', ['id' => $id]);
            }

            $nom = trim((string) $request->request->get('nom'));
            $equipement->setNom($nom);
            $equipement->setCategorie($request->request->get('categorie') ?: null);
            $equipement->setPrix($request->request->get('prix') ?: '0.00');
            $equipement->setQuantiteDisponible((int) $request->request->get('quantiteDisponible'));
            $equipement->setStatut($request->request->get('statut') ?: 'disponible');
            $equipement->setDescription($request->request->get('description') ?: null);

            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string) $slugger->slug($originalFilename ?: 'equipment');

                try {
                    $processedFilename = $imageProcessor->processAndStore($imageFile, $safeFilename);
                    $oldImage = $equipement->getImage();
                    $equipement->setImage($processedFilename);
                    $imageProcessor->deleteIfExists($oldImage);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors du traitement AI de l\'image');
                }
            }

            $errors = $validator->validate($equipement);
            if (count($errors) > 0) {
                $equipements = $equipementRepository->findBy(
                    ['utilisateur' => $user],
                    ['dateAjout' => 'DESC']
                );

                $commandes = $commandeRepository->createQueryBuilder('c')
                    ->select('DISTINCT c')
                    ->leftJoin('c.equipements', 'e')
                    ->addSelect('e')
                    ->andWhere('e.utilisateur = :user')
                    ->setParameter('user', $user)
                    ->orderBy('c.dateCommande', 'DESC')
                    ->getQuery()
                    ->getResult();

                $messages = [];
                foreach ($errors as $error) {
                    $messages[] = $error->getMessage();
                }

                return $this->render('FrontOffice/proprietaire/proprietaire.html.twig', [
                    'equipements' => $equipements,
                    'commandes' => $commandes,
                    'mode' => 'edit',
                    'selected' => $equipement,
                    'form_errors' => $messages,
                ]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Equipement modifie avec succes !');
            return $this->redirectToRoute('front_proprietaire_equipements');
        }

        $equipements = $equipementRepository->findBy(
            ['utilisateur' => $user],
            ['dateAjout' => 'DESC']
        );

        $commandes = $commandeRepository->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->leftJoin('c.equipements', 'e')
            ->addSelect('e')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('FrontOffice/proprietaire/proprietaire.html.twig', [
            'equipements' => $equipements,
            'commandes' => $commandes,
            'mode' => 'edit',
            'selected' => $equipement,
        ]);
    }

    #[Route('/proprietaire/equipements/{id}/delete', name: 'front_proprietaire_equipements_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        EquipementRepository $equipementRepository,
        ImageProcessor $imageProcessor
    ): Response {
        $user = $this->getUser();
        $equipement = $equipementRepository->findOneBy(['id' => $id, 'utilisateur' => $user]);

        if (!$equipement) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        if ($this->isCsrfTokenValid('equipement_delete' . $equipement->getId(), $request->request->get('_token'))) {
            $imageProcessor->deleteIfExists($equipement->getImage());
            $entityManager->remove($equipement);
            $entityManager->flush();
            $this->addFlash('success', 'Equipement supprime avec succes !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('front_proprietaire_equipements');
    }

    #[Route('/proprietaire/commandes/{id}/cycle-statut', name: 'front_proprietaire_commandes_cycle_statut', methods: ['POST'])]
    public function cycleCommandeStatut(
        Request $request,
        Commande $commande,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('commande_cycle_statut' . $commande->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('front_proprietaire_equipements');
        }

        $owned = false;
        foreach ($commande->getEquipements() as $equipement) {
            if ($equipement->getUtilisateur() && $equipement->getUtilisateur()->getId() === $user->getId()) {
                $owned = true;
                break;
            }
        }

        if (!$owned) {
            throw $this->createAccessDeniedException('Commande non autorisee.');
        }

        $cycle = [
            Commande::STATUT_EN_ATTENTE,
            Commande::STATUT_EN_PREPARATION,
            Commande::STATUT_EXPEDIE,
            Commande::STATUT_LIVRE,
            Commande::STATUT_ANNULE,
        ];

        $current = $commande->getStatutCommande();
        $index = array_search($current, $cycle, true);
        $next = $cycle[0];
        if ($index !== false) {
            $next = $cycle[($index + 1) % count($cycle)];
        }

        $commande->setStatutCommande($next);
        $entityManager->flush();

        $this->addFlash('success', 'Statut de la commande mis a jour.');
        return $this->redirectToRoute('front_proprietaire_equipements');
    }
}
