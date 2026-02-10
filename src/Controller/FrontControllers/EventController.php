<?php

namespace App\Controller\FrontOffice;

use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Event;
use App\Entity\Participation;

#[Route('/eventsFront', name: 'front_events_')]
class EventController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findBy(
            ['statut' => 'PUBLIE'],
            ['dateDebut' => 'DESC']
        );

        return $this->render('FrontOffice/events/index.html.twig', [
            'events' => $events
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        int $id,
        EventRepository $eventRepository,
        ParticipationRepository $participationRepo
    ): Response {
        $event = $eventRepository->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        if ($event->getStatut() !== 'PUBLIE') {
            throw $this->createNotFoundException('Cet événement n\'est pas publié');
        }

        $user = $this->getUser();

        // Nombre de participants
        $participantsCount = $participationRepo->count(['event' => $event]);

        // Test si complet
        $isFull = $event->getCapaciteMax() !== null
            && $participantsCount >= $event->getCapaciteMax();

        // Test si déjà participant
        $isParticipating = false;
        if ($user) {
            $isParticipating = (bool) $participationRepo->findOneBy([
                'event' => $event,
                'utilisateur' => $user
            ]);
        }

        return $this->render('FrontOffice/events/show.html.twig', [
            'event' => $event,
            'participantsCount' => $participantsCount,
            'isFull' => $isFull,
            'isParticipating' => $isParticipating
        ]);
    }

    #[Route('/{id}/participer', name: 'participer', methods: ['POST'])]
    public function participer(
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('front_participer_' . $event->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $user = $this->getUser();

        // Déjà participant ?
        $existing = $participationRepo->findOneBy([
            'event' => $event,
            'utilisateur' => $user
        ]);

        if ($existing) {
            $this->addFlash('warning', 'Vous participez déjà à cet événement.');
            return $this->redirectToRoute('front_events_show', ['id' => $event->getId()]);
        }

        // Capacité atteinte ?
        $count = $participationRepo->count(['event' => $event]);

        if ($event->getCapaciteMax() !== null && $count >= $event->getCapaciteMax()) {
            $this->addFlash('danger', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('front_events_show', ['id' => $event->getId()]);
        }

        // Création participation
        $p = new Participation();
        $p->setEvent($event);
        $p->setUtilisateur($user);
        $p->setDateInscription(new \DateTimeImmutable());
        $p->setStatut('INSCRIT');

        $em->persist($p);
        $em->flush();



        return $this->redirectToRoute('front_events_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/annuler', name: 'annuler', methods: ['POST'])]
    public function annuler(
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('front_annuler_' . $event->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $user = $this->getUser();

        $existing = $participationRepo->findOneBy([
            'event' => $event,
            'utilisateur' => $user
        ]);

        if (!$existing) {
            $this->addFlash('warning', 'Vous ne participez pas à cet événement.');
            return $this->redirectToRoute('front_events_show', ['id' => $event->getId()]);
        }

        $em->remove($existing);
        $em->flush();

        $this->addFlash('success', 'Participation annulée.');

        return $this->redirectToRoute('front_events_show', ['id' => $event->getId()]);
    }
}
