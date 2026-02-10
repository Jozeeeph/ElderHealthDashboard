<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // ✅ Page d'accueil publique (ou redirection)
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si admin connecté ➜ rediriger vers sa page d'accueil admin
        if ($user instanceof Utilisateur && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_home');
        }

        // Sinon, page d'accueil (publique / front)
        return $this->render('front/home.html.twig'); // adapte si tu as un autre twig
    }

    // ✅ Page d'accueil ADMIN
    #[Route('/admin/home', name: 'admin_home')]
    public function adminHome(): Response
    {
        return $this->render('BackOffice/home/index.html.twig');
    }

    // ✅ Route de test: envoi email DIRECT (sans reset password)
    #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(MailerInterface $mailer): Response
    {
        $email = (new TemplatedEmail())
            ->from(new Address('arijbejaoui1991@gmail.com', 'ElderHealthCare Test'))
            ->to('arijbejaoui1991@gmail.com')
            ->subject('TEST EMAIL DIRECT - ElderHealthCare')
            ->htmlTemplate('emails/test_email.html.twig')
            ->context([
                'now' => new \DateTimeImmutable(),
            ]);

        $mailer->send($email);

        return new Response('✅ Email envoyé ! Vérifie Gmail (Spam / Tous les messages).');
    }
}
