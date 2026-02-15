<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class ForumMailerService
{
    public function __construct(
        private MailerInterface $mailer
    ) {
    }

    public function sendCommentNotification(
        string $toEmail,
        string $toName,
        string $postTitle,
        string $commentSummary
    ): void {
        $email = (new TemplatedEmail())
            ->from('noreply@elderhealthcare.com') // ✅ OBLIGATOIRE
            ->to($toEmail)
            ->subject('Nouveau commentaire sur votre publication')
            ->htmlTemplate('emails/forum_comment_notification.html.twig')
            ->context([
                'toName' => $toName,
                'postTitle' => $postTitle,
                'commentSummary' => $commentSummary,
            ]);
        $this->mailer->send($email);
    }
}
