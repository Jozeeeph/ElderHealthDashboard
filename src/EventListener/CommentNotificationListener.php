<?php

namespace App\EventListener;

use App\Event\CommentCreatedEvent;
use App\Service\AiSummaryService;
use App\Service\ForumMailerService;

class CommentNotificationListener
{
    public function __construct(
        private AiSummaryService $aiSummary,
        private ForumMailerService $mailer
    ) {}

    public function __invoke(CommentCreatedEvent $event): void
    {
        $comment = $event->getComment();
        $post = $comment->getPost();

        if (!$post) {
            return;
        }

        $owner = $post->getUtilisateur(); // adapte si ton getter s'appelle autrement
        if (!$owner || !$owner->getEmail()) {
            return;
        }

        // option: ne pas s’envoyer de mail à soi-même
        if ($comment->getUtilisateur() && $comment->getUtilisateur()->getId() === $owner->getId()) {
            return;
        }

        $summary = $this->aiSummary->summarize($comment->getContent());

        $this->mailer->sendCommentNotification(
            $owner->getEmail(),
            $owner->getPrenom() ?? 'Utilisateur',
            $post->getTitle() ?? 'Votre publication',
            $summary
        );
    }
}
