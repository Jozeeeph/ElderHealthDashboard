<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('_username', '');
        $password = (string) $request->request->get('_password', '');
        $csrf = (string) $request->request->get('_csrf_token', '');

        return new Passport(
            new UserBadge($email, function (string $userIdentifier) {
                /** @var Utilisateur|null $user */
                $user = $this->em->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Email ou mot de passe invalide.');
                }

                // ✅ Bloquer ADMIN AVANT la connexion via /login
                if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                    throw new CustomUserMessageAuthenticationException(
                        "Compte n'est pas autorisé à se connecter ici. Veuillez utiliser la page de connexion admin."
                    );
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrf),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): RedirectResponse {
        $roles = $token->getUser()->getRoles();

        if (in_array('ROLE_PATIENT', $roles, true)) {
            return new RedirectResponse('/patient');
        }

        if (in_array('ROLE_PERSONNEL_MEDICAL', $roles, true)) {
            return new RedirectResponse('/infermier');
        }

        return new RedirectResponse('/');
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
