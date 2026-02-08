<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\TypeEvent;
use App\Entity\Participation;
use App\Entity\Utilisateur;
use App\Form\EventType;
use App\Form\TypeEventType;
use App\Repository\EventRepository;
use App\Repository\TypeEventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;


#[Route('/events', name: 'event_')]
class EventController extends AbstractController
{


    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('BackOffice/event/index.html.twig', [
            'events' => $eventRepository->findBy([], ['dateDebut' => 'DESC']),
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
            return $this->redirectToRoute('event_index');
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
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l’upload');
                }

                $event->setImage($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Événement modifié');
            return $this->redirectToRoute('event_index');
        }

        return $this->render('BackOffice/event/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé 🗑️');
        }

        return $this->redirectToRoute('event_index');
    }


    // TypeEvent


    #[Route('/types', name: 'type_index', methods: ['GET'])]
    public function typeIndex(TypeEventRepository $typeEventRepository): Response
    {
        return $this->render('BackOffice/type_event/index.html.twig', [
            'types' => $typeEventRepository->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/types/new', name: 'type_new', methods: ['GET', 'POST'])]
    public function typeNew(Request $request, EntityManagerInterface $em): Response
    {
        $type = new TypeEvent();

        $form = $this->createForm(TypeEventType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($type);
            $em->flush();

            $this->addFlash('success', 'Type créé ✅');
            return $this->redirectToRoute('event_type_index');
        }

        return $this->render('BackOffice/type_event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/types/{id}/edit', name: 'type_edit', methods: ['GET', 'POST'])]
    public function typeEdit(TypeEvent $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TypeEventType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Type modifié ✅');
            return $this->redirectToRoute('event_type_index');
        }

        return $this->render('BackOffice/type_event/edit.html.twig', [
            'type' => $type,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/types/{id}', name: 'type_delete', methods: ['POST'])]
    public function typeDelete(TypeEvent $type, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_type_' . $type->getId(), (string) $request->request->get('_token'))) {
            $em->remove($type);
            $em->flush();
            $this->addFlash('success', 'Type supprimé 🗑️');
        }

        return $this->redirectToRoute('event_type_index');
    }

    // =========================
    // PARTICIPATION (Bouton Participer / Annuler)
    // =========================

    #[Route('/{id}/participer', name: 'participer', methods: ['POST'])]
    public function participer(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepository
    ): Response {
        // CSRF
        if (!$this->isCsrfTokenValid('participer_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('event_index');
        }

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté pour participer.');
            return $this->redirectToRoute('app_login'); // adapte si ton login route est différente
        }

        // déjà inscrit ?
        $existing = $participationRepository->findOneBy([
            'event' => $event,
            'utilisateur' => $user,
        ]);

        if ($existing) {
            $this->addFlash('info', 'Vous participez déjà à cet événement 🙂');
            return $this->redirectToRoute('event_index');
        }

        $p = new Participation();
        if (method_exists($p, 'setDateInscription')) {
            $p->setDateInscription(new \DateTimeImmutable());
        }
        if (method_exists($p, 'setStatut')) {
            $p->setStatut('CONFIRMEE');
        }

        $p->setEvent($event);
        $p->setUtilisateur($user);

        $em->persist($p);
        $em->flush();

        $this->addFlash('success', 'Participation enregistrée ✅');
        return $this->redirectToRoute('event_index');
    }

    #[Route('/{id}/annuler', name: 'annuler', methods: ['POST'])]
    public function annuler(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepository
    ): Response {
        if (!$this->isCsrfTokenValid('annuler_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('event_index');
        }

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $existing = $participationRepository->findOneBy([
            'event' => $event,
            'utilisateur' => $user,
        ]);

        if (!$existing) {
            $this->addFlash('info', 'Vous n’êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('event_index');
        }

        $em->remove($existing);
        $em->flush();

        $this->addFlash('success', 'Participation annulée ✅');
        return $this->redirectToRoute('event_index');
    }
}
