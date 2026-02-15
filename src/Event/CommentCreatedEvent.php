<?php

namespace App\Event;

use App\Entity\Commentaire;

class CommentCreatedEvent
{
    public function __construct(private Commentaire $comment)
    {
    }

    public function getComment(): Commentaire
    {
        return $this->comment;
    }
}
