<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Repository\TypeEventRepository;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/events', name: 'admin_event_')]
class AdminEventController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request, // 👈 AJOUTER REQUEST
        EventRepository $eventRepository,
        TypeEventRepository $typeEventRepository // 👈 AJOUTER LE REPOSITORY DES TYPES
    ): Response {
        // Récupérer tous les types d'événements pour le filtre
        $eventTypes = $typeEventRepository->findAll();

        // Récupérer les filtres depuis la requête
        $status = $request->query->get('status', 'all'); // all, upcoming, passed
        $typeId = $request->query->get('type');

        // Récupérer tous les événements
        $allEvents = $eventRepository->findBy([], ['dateDebut' => 'DESC']);

        // Date actuelle
        $now = new \DateTime();

        // Filtrer selon le statut
        $filteredEvents = [];
        foreach ($allEvents as $event) {
            $isPassed = $event->getDateDebut() <= $now;

            // Filtre par statut
            if ($status === 'passed' && !$isPassed)
                continue;
            if ($status === 'upcoming' && $isPassed)
                continue;

            // Filtre par type
            if ($typeId && $event->getType() && $event->getType()->getId() != $typeId)
                continue;
            if ($typeId && !$event->getType())
                continue;

            $filteredEvents[] = $event;
        }

        // Compter les événements passés pour le message
        $passedCount = 0;
        foreach ($allEvents as $event) {
            if ($event->getDateDebut() <= $now) {
                $passedCount++;
            }
        }

        return $this->render('BackOffice/event/index.html.twig', [
            'events' => $filteredEvents,
            'eventTypes' => $eventTypes, // 👈 IMPORTANT: passer les types au template
            'totalEvents' => count($allEvents),
            'passedCount' => $passedCount,
            'currentStatus' => $status,
            'currentType' => $typeId
        ]);
    }

    // ✅ NOUVEAU : page détails + participants
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepository $eventRepository): Response
    {
        $event = $eventRepository->findOneWithParticipations($id);

        if (!$event) {
            throw $this->createNotFoundException("Événement introuvable.");
        }

        return $this->render('BackOffice/event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $event = new Event();
        $event->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);

                $extension = $imageFile->guessExtension()
                    ?: $imageFile->getClientOriginalExtension()
                    ?: 'jpg';

                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $event->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur upload : ' . $e->getMessage());
                    return $this->render('BackOffice/event/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            }

            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('BackOffice/event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Event $event, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $event->setUpdatedAt(new \DateTimeImmutable());

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);

                $extension = $imageFile->guessExtension()
                    ?: $imageFile->getClientOriginalExtension()
                    ?: 'jpg';

                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $event->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l’upload : ' . $e->getMessage());
                }
            }

            $em->flush();
            $this->addFlash('success', 'Événement modifié');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('BackOffice/event/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            foreach ($event->getParticipations() as $p) {
                $em->remove($p);
            }

            $em->remove($event);
            $em->flush();

            $this->addFlash('success', 'Événement supprimé 🗑️');
        }

        return $this->redirectToRoute('admin_event_index');
    }
}
