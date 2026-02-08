<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new Utilisateur();

        // Demande de compte par défaut
        $user->setAccountStatus(Utilisateur::STATUS_PENDING);
        $user->setIsActive(false);

        // rôle par défaut si l'utilisateur ne choisit pas (optionnel)
        $user->setRoleMetier(Role::PATIENT->value);

        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password
            $hashed = $hasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashed);

            // ✅ Important: si le rôle vient du form, il est déjà dans $user->roleMetier
            // et setRoleMetier() synchronise roles[] automatiquement.

            $em->persist($user);
            $em->flush();

            return $this->render('BackOffice/user/message.html.twig', [
                'message' => "Demande de compte envoyée ✅ En attente de validation par l'administrateur.",
            ]);
        }

        return $this->render('BackOffice/user/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
