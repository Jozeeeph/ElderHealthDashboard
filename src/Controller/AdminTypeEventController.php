<?php

namespace App\Controller;

use App\Entity\TypeEvent;
use App\Form\TypeEventType;
use App\Repository\TypeEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/typeEvent', name: 'admin_typeevent_')]
class AdminTypeEventController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(TypeEventRepository $typeEventRepository): Response
    {
        return $this->render('BackOffice/type_event/index.html.twig', [
            'types' => $typeEventRepository->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $type = new TypeEvent();

        $form = $this->createForm(TypeEventType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($type);
            $em->flush();

            $this->addFlash('success', 'Type créé ✅');
            return $this->redirectToRoute('admin_typeevent_index');
        }

        return $this->render('BackOffice/type_event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(TypeEvent $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TypeEventType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Type modifié ✅');
            return $this->redirectToRoute('admin_typeevent_index');
        }

        return $this->render('BackOffice/type_event/edit.html.twig', [
            'type' => $type,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(TypeEvent $type, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_type_' . $type->getId(), (string) $request->request->get('_token'))) {
            $em->remove($type);
            $em->flush();
            $this->addFlash('success', 'Type supprimé 🗑️');
        }

        return $this->redirectToRoute('admin_typeevent_index');
    }
}
