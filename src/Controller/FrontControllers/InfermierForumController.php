<?php

namespace App\Controller\FrontControllers;

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

#[Route('/infermier', name: 'front_infermier_forum_')]
#[IsGranted('ROLE_PERSONNEL_MEDICAL')]
class InfermierForumController extends AbstractController
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
            'post_create_route' => 'front_infermier_forum_post_new',
            'comment_create_route' => 'front_infermier_forum_comment_new',
        ]);
    }

    #[Route('/post/new', name: 'post_new', methods: ['POST'])]
    public function createPost(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $post = new Post();
        $post->setUtilisateur($this->getUser());
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
                $this->addFlash('danger', 'Erreur lors du telechargement de l image.');
            }
        }

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Publication ajoutee avec succes.');

        return $this->redirectToRoute('front_infermier_forum_index');
    }

    #[Route('/post/{postId}/comment/new', name: 'comment_new', methods: ['POST'])]
    public function createComment(
        int $postId,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $post = $em->getRepository(Post::class)->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable');
        }

        $content = trim((string) $request->request->get('content'));
        if ($content === '') {
            $this->addFlash('danger', 'Le commentaire ne peut pas etre vide.');
            return $this->redirectToRoute('front_infermier_forum_index');
        }

        $comment = new Commentaire();
        $comment->setPost($post);
        $comment->setUtilisateur($this->getUser());
        $comment->setContent($content);

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajoute.');

        return $this->redirectToRoute('front_infermier_forum_index');
    }
}
