<?php

namespace App\Controller\FrontOffice;

use App\Entity\Commentaire;
use App\Entity\Post;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Event\CommentCreatedEvent;
use App\Event\PostCreatedEvent;


#[Route('/patient', name: 'forum_post_forum_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ForumController extends AbstractController
{
    #[Route('/forum', name: 'index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findBy(
            ['status' => 'ACTIVE'],
            ['dateDeCreation' => 'DESC']
        );

        return $this->render('FrontOffice/forum/index.html.twig', [
            'posts' => $posts,
            'post_create_route' => 'forum_post_forum_post_new',
            'comment_create_route' => 'forum_post_forum_comment_new',
        ]);
    }

    #[Route('/post/new', name: 'post_new', methods: ['POST'])]
    public function createPost(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        EventDispatcherInterface $dispatcher
    ): Response {

        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        $post = new Post();
        $post->setUtilisateur($user);
        $post->setTitle(trim((string) $request->request->get('title')));
        $post->setContent(trim((string) $request->request->get('content')));

        $imageFile = $request->files->get('image');

        if ($imageFile) {
            $originalName = pathinfo(
                $imageFile->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $safeName = $slugger->slug($originalName);
            $newFilename = $safeName . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('post_images_dir'),
                    $newFilename
                );
                $post->setImageName($newFilename);
            } catch (FileException $e) {
                $this->addFlash('danger', 'Erreur upload image.');
            }
        }

        $em->persist($post);
        $em->flush();

        // déclenche AI agent
        $dispatcher->dispatch(new PostCreatedEvent($post));

        $this->addFlash('success', 'Publication ajoutée avec succès.');

        return $this->redirectToRoute('forum_post_forum_index');
    }


    #[Route('/post/{postId}/comment/new', name: 'comment_new', methods: ['POST'])]
    public function createComment(
        int $postId,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher
    ): Response {
        $post = $em->getRepository(Post::class)->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable');
        }

        $content = trim((string) $request->request->get('content'));
        if ($content === '') {
            $this->addFlash('danger', 'Le commentaire ne peut pas etre vide.');
            return $this->redirectToRoute('forum_post_forum_index');
        }

        $comment = new Commentaire();
        $comment->setPost($post);
        $comment->setUtilisateur($this->getUser());
        $comment->setContent($content);

        $em->persist($comment);
        $em->flush();

        $dispatcher->dispatch(new CommentCreatedEvent($comment));


        $this->addFlash('success', 'Commentaire ajoute.');

        return $this->redirectToRoute('forum_post_forum_index');
    }
}
