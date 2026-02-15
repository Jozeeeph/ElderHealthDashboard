<?php

namespace App\EventListener;

use App\Event\PostCreatedEvent;
use App\Entity\Commentaire;
use App\Service\AiMedicalAnalyzer;
use Doctrine\ORM\EntityManagerInterface;

class AiMedicalAgentListener
{
    public function __construct(
        private AiMedicalAnalyzer $ai,
        private EntityManagerInterface $em
    ) {
    }

    public function __invoke(PostCreatedEvent $event)
    {
        $post = $event->getPost();

        if (!$this->ai->needsMedicalReply($post->getContent())) {
            return;
        }

        $comment = new Commentaire();
        $comment->setPost($post);

        // ✅ FIX IMPORTANT
        $comment->setUtilisateur($post->getUtilisateur());

        $comment->setContent(
            $this->ai->generateReply($post->getContent())
        );

        $comment->setIsAi(true);

        $this->em->persist($comment);
        $this->em->flush();
    }

}
