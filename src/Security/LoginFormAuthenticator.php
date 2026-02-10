<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        return new Passport(
            new UserBadge((string) $request->request->get('_username', '')),
            new PasswordCredentials((string) $request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token', '')),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): RedirectResponse {

        $roles = $token->getUser()->getRoles();

        // 🧑‍⚕️ PATIENT → /patient
        if (in_array('ROLE_PATIENT', $roles, true)) {
            return new RedirectResponse('/patient');
        }

        // 🔐 ADMIN → /admin/users
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse('/admin/users');
        }

        // 👩‍⚕️ INFIRMIER / PERSONNEL MEDICAL
        if (in_array('ROLE_PERSONNEL_MEDICAL', $roles, true)) {
            return new RedirectResponse('/infermier');
        }

        // 👩‍⚕️ INFIRMIER / PERSONNEL MEDICAL
        if (in_array('ROLE_PROPRIETAIRE_MEDICAUX', $roles, true)) {
            return new RedirectResponse('/proprietaire');
        }

        // fallback
        return new RedirectResponse('/');
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
