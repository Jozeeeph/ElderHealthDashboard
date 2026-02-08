<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UtilisateurChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if ($user->isActive() === false) {
            throw new CustomUserMessageAccountStatusException("Compte désactivé. Veuillez contacter l'administrateur.");
        }

        if ($user->getAccountStatus() !== Utilisateur::STATUS_APPROVED) {
            throw new CustomUserMessageAccountStatusException("Compte en attente de validation par l'administrateur.");
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
