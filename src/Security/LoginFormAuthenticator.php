<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
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
        private EntityManagerInterface $em,
        private MailerInterface $mailer
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
        /** @var Utilisateur $user */
        $user = $token->getUser();
        $roles = $user->getRoles();

        // ✅ ADMIN => PIN session + email + redirect /admin/pin
        if (in_array('ROLE_ADMIN', $roles, true)) {

            $pin = (string) random_int(100000, 999999);

            // Stockage en session (hash + expiry)
            $session = $request->getSession();
            $session->set('admin_pin_hash', password_hash($pin, PASSWORD_BCRYPT));
            $session->set('admin_pin_expires', time() + 300); // 5 minutes
            $session->set('admin_2fa_verified', false);

            // Envoi email du PIN
            $emailMsg = (new TemplatedEmail())
                ->from(new Address('arijbejaoui1991@gmail.com', 'ElderHealthCare'))
                ->to((string) $user->getEmail())
                ->subject('Code PIN Admin')
                ->htmlTemplate('security/admin_pin_email.html.twig')
                ->context([
                    'pin' => $pin,
                    'expiresMinutes' => 5,
                ]);

            $this->mailer->send($emailMsg);

            return new RedirectResponse($this->urlGenerator->generate('admin_pin'));
        }

        // ✅ autres rôles
        if (in_array('ROLE_PATIENT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_patient_interfce'));
        }

        if (in_array('ROLE_PERSONNEL_MEDICAL', $roles, true)) {
            return new RedirectResponse('/infermier');
        }

        if (in_array('ROLE_PROPRIETAIRE_MEDICAUX', $roles, true)) {
            return new RedirectResponse('/proprietaire/equipements');
        }

        return new RedirectResponse('/');
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
