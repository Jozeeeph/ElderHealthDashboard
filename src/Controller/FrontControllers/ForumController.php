<?php

namespace App\Controller\FrontOffice;

use App\Entity\Post;
use App\Entity\Commentaire;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum/posts', name: 'forum_post_forum_')]
#[IsGranted('ROLE_PATIENT')]
class ForumController extends AbstractController
{
    /**
     * 🧵 Forum feed (FrontOffice)
     */
    #[Route('/forum', name: 'index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findBy(
            ['status' => 'ACTIVE'],
            ['dateDeCreation' => 'DESC']
        );

        return $this->render('FrontOffice/forum/index.html.twig', [
            'posts' => $posts,
        ]);
    }



    /**
     * ➕ Create new post (modal submit)
     */
    #[Route('/post/new', name: 'post_new', methods: ['POST'])]
    public function createPost(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $post = new Post();
        $post->setUtilisateur($this->getUser());
        $post->setTitle(trim($request->request->get('title')));
        $post->setContent(trim($request->request->get('content')));

        // 📷 Optional image upload
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
                $this->addFlash('danger', 'Erreur lors du téléchargement de l’image.');
            }
        }

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Publication ajoutée avec succès.');

        return $this->redirectToRoute('forum_post_forum_index');
    }

    /**
     * 💬 Add comment to a post (modal submit)
     */
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

        $content = trim($request->request->get('content'));
        if ($content === '') {
            $this->addFlash('danger', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('patient_forum_index');
        }

        $comment = new Commentaire();
        $comment->setPost($post);
        $comment->setUtilisateur($this->getUser());
        $comment->setContent($content);

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajouté.');

        return $this->redirectToRoute('forum_post_forum_index');
    }
}
