<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Post;
use App\Form\CommentaireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum/posts/{postId}/commentaires', name: 'forum_comment_')]
class CommentaireController extends AbstractController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(int $postId, Request $request, EntityManagerInterface $em): Response
    {
        $post = $em->getRepository(Post::class)->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        $comment = new Commentaire();
        $comment->setPost($post);

        $form = $this->createForm(CommentaireType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();

            return $this->redirectToRoute('forum_post_index');
        }

        return $this->render('commentaire/new.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $postId, Commentaire $comment, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommentaireType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('forum_post_index');
        }
        if ($comment->getPost()?->getId() !== $postId) {
            throw $this->createNotFoundException('Comment does not belong to this post');
        }

        return $this->render('commentaire/edit.html.twig', [
            'postId' => $postId,
            'comment' => $comment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $postId, Commentaire $comment, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
        }
        if ($comment->getPost()?->getId() !== $postId) {
            throw $this->createNotFoundException('Comment does not belong to this post');
        }

        return $this->redirectToRoute('forum_post_index');
    }
}
