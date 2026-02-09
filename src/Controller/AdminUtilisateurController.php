<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurAdminType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_users_')]
class AdminUtilisateurController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UtilisateurRepository $repo): Response
    {
        return $this->render('BackOffice/user/index.html.twig', [
            'pendingUsers' => $repo->findBy(['accountStatus' => Utilisateur::STATUS_PENDING]),
            'allUsers' => $repo->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Utilisateur $user): Response
    {
        return $this->render('BackOffice/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Utilisateur $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UtilisateurAdminType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->render('BackOffice/user/message.html.twig', [
                'message' => 'Utilisateur modifié ✅',
            ]);
        }

        return $this->render('BackOffice/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Utilisateur $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();

        return $this->render('BackOffice/user/message.html.twig', [
            'message' => 'Utilisateur supprimé 🗑️',
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
public function toggleActive(Utilisateur $user, EntityManagerInterface $em): Response
{
    $newState = !$user->isActive();
    $user->setIsActive($newState);
    $em->flush();

    $message = $newState
        ? "✅ Compte ACTIVÉ avec succès."
        : "⛔ Compte DÉSACTIVÉ avec succès.";

    return $this->render('BackOffice/user/message.html.twig', [
        'message' => $message,
        'return_route' => 'admin_users_index',
    ]);
}


    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(Utilisateur $user, EntityManagerInterface $em): Response
    {
        $user->setAccountStatus(Utilisateur::STATUS_APPROVED);
        $user->setIsActive(true);
        $em->flush();

        return $this->render('BackOffice/user/message.html.twig', [
            'message' => 'Compte accepté ✅',
        ]);
    }

    #[Route('/{id}/refuse', name: 'refuse', methods: ['POST'])]
    public function refuse(Utilisateur $user, EntityManagerInterface $em): Response
    {
        $user->setAccountStatus(Utilisateur::STATUS_REFUSED);
        $user->setIsActive(false);
        $em->flush();

        return $this->render('BackOffice/user/message.html.twig', [
            'message' => 'Demande refusée ❌',
        ]);
    }
}
