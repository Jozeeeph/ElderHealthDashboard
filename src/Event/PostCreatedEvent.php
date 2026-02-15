<?php

namespace App\Event;

use App\Entity\Post;

class PostCreatedEvent
{
    public function __construct(private Post $post){}

    public function getPost(): Post
    {
        return $this->post;
    }
}
