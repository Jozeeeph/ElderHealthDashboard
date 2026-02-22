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
use App\Service\AiSummaryService;
use App\Service\ExternalAiService;
use Symfony\Component\HttpFoundation\JsonResponse;


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

    #[Route('/comment/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    public function deleteComment(
        Commentaire $comment,
        EntityManagerInterface $em
    ): Response {

        if ($comment->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($comment);
        $em->flush();

        return $this->redirectToRoute('forum_post_forum_index');
    }

    #[Route('/comment/{id}/edit-inline', name: 'comment_edit_inline', methods: ['POST'])]
    public function editInline(
        Commentaire $comment,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        if ($comment->getUtilisateur() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $comment->setContent($data['content'] ?? '');
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/ai/summarize/{id}', name: 'ai_summary')]
    public function summarize(Post $post, AiSummaryService $ai): Response
    {
        $combinedText = '';

        foreach ($post->getCommentaires() as $c) {
            $combinedText .= $c->getContent() . ' ';
        }

        if (!$combinedText) {
            return $this->json([
                'summary' => 'Aucun commentaire à analyser.'
            ]);
        }

        return $this->json([
            'summary' => $ai->summarize($combinedText)
        ]);
    }

    #[Route('/post/{id}/delete', name: 'forum_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, EntityManagerInterface $em): Response
    {
        if ($post->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($post);
        $em->flush();

        return $this->redirectToRoute('forum_post_forum_index');
    }

    #[Route('/post/{id}/toggle', name: 'forum_post_toggle', methods: ['POST'])]
    public function togglePost(Post $post, EntityManagerInterface $em): Response
    {
        if ($post->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $post->setStatus(
            $post->getStatus() === 'ACTIVE'
            ? 'INACTIVE'
            : 'ACTIVE'
        );

        $em->flush();

        return $this->redirectToRoute('forum_post_forum_index');
    }

    #[Route('/post/{id}/edit-inline', name: 'post_edit_inline', methods: ['POST'])]
    public function editPostInline(
        Post $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        if ($post->getUtilisateur() !== $this->getUser()) {
            return $this->json(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $post->setTitle($data['title']);
        $post->setContent($data['content']);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/patient/ai-chat', name: 'medical_ai_chat')]
    public function aiChat(): Response
    {
        return $this->render('FrontOffice/ai/chat.html.twig');
    }

    #[Route('/patient/ai-chat/send', name: 'ai_chat_send', methods: ['POST'])]
    public function sendAi(Request $request,ExternalAiService $ai): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $reply = $ai->ask($data['message']);

        return $this->json([
            'reply' => $reply
        ]);
    }

}
