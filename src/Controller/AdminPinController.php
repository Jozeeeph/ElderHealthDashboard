<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class AdminPinController extends AbstractController
{
    #[Route('/admin/pin', name: 'admin_pin', methods: ['GET', 'POST'])]
    public function pin(Request $request): Response
    {
        $user = $this->getUser();

        // ✅ sécurité : uniquement admin
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            $enteredPin = trim((string) $request->request->get('pin', ''));

            $hash = $session->get('admin_pin_hash');
            $expires = (int) $session->get('admin_pin_expires', 0);

            // ✅ si pas de code en session => l’admin doit se reconnecter
            if (!$hash || $expires === 0) {
                $this->addFlash('error', 'Aucun code PIN trouvé. Reconnectez-vous.');
                return $this->redirectToRoute('app_login');
            }

            // ✅ code expiré
            if (time() > $expires) {
                $this->addFlash('error', 'Code expiré. Reconnectez-vous.');
                $session->remove('admin_pin_hash');
                $session->remove('admin_pin_expires');
                $session->set('admin_2fa_verified', false);

                return $this->redirectToRoute('app_login');
            }

            // ✅ vérifier le PIN
            if (password_verify($enteredPin, $hash)) {
                $session->set('admin_2fa_verified', true);

                // sécurité: supprimer le PIN après usage
                $session->remove('admin_pin_hash');
                $session->remove('admin_pin_expires');

                // ✅ IMPORTANT: remplace ici par ta vraie route admin si besoin
                return $this->redirectToRoute('admin_home');
            }

            $this->addFlash('error', 'Code incorrect.');
        }

        return $this->render('security/admin_pin.html.twig');
    }

    #[Route('/admin/pin/resend', name: 'admin_pin_resend', methods: ['POST'])]
    public function resend(Request $request, MailerInterface $mailer): Response
    {
        $user = $this->getUser();

        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();

        // ✅ regen PIN
        $pin = (string) random_int(100000, 999999);

        $session->set('admin_pin_hash', password_hash($pin, PASSWORD_BCRYPT));
        $session->set('admin_pin_expires', time() + 300); // 5 minutes
        $session->set('admin_2fa_verified', false);

        // ✅ envoi mail
        $emailMsg = (new TemplatedEmail())
            ->from(new Address('arijbejaoui1991@gmail.com', 'ElderHealthCare'))
            ->to((string) $user->getEmail())
            ->subject('Nouveau Code PIN Admin')
            ->htmlTemplate('security/admin_pin_email.html.twig')
            ->context([
                'pin' => $pin,
                'expiresMinutes' => 5,
            ]);

        $mailer->send($emailMsg);

        $this->addFlash('success', 'Nouveau code envoyé.');
        return $this->redirectToRoute('admin_pin');
    }
}
