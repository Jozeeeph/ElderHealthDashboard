<?php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostType;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;


#[Route('/forum/posts', name: 'forum_post_')]
class ForumPostController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        return $this->render('BackOffice/forum_post/index.html.twig', [
            'posts' => $postRepository->findAllWithComments(),
        ]);
    }


    #[Route('/forum', name: 'forum_index')]
    public function index2(PostRepository $postRepository): Response
    {
        return $this->render('FrontOffice/forum/index.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }


    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Save author
            $post->setUtilisateur($this->getUser());

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('post_images_dir'), $newFilename);
                    $post->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Image upload failed.');
                }
            }

            $em->persist($post);
            $em->flush();

            return $this->redirectToRoute('forum_post_forum_index'); // ✅ front forum page (adjust if needed)
        }

        return $this->render('BackOffice/forum_post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Post $post, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('post_images_dir'), $newFilename);

                    // ✅ option: delete old file
                    $old = $post->getImageName();
                    if ($old) {
                        $oldPath = $this->getParameter('post_images_dir') . '/' . $old;
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    $post->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Image upload failed.');
                }
            }

            $em->flush();
            return $this->redirectToRoute('forum_post_index');
        }

        return $this->render('BackOffice/forum_post/edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_post_' . $post->getId(), $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
        }

        return $this->redirectToRoute('forum_post_index');
    }
}
