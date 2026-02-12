<?php

namespace App\Controller\Patient;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\Utilisateur;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PATIENT')]
#[Route('/patient/event', name: 'patient_event_')]
class PatientEventController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('FrontOffice/event/index.html.twig', [
            'events' => $eventRepository->findBy([], ['dateDebut' => 'DESC']),
        ]);
    }

    #[Route('/{id}/participer', name: 'participer', methods: ['POST'])]
    public function participer(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepository
    ): Response {
        if (!$this->isCsrfTokenValid('participer_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('patient_event_index');
        }

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté pour participer.');
            return $this->redirectToRoute('app_login');
        }

        $existing = $participationRepository->findOneBy([
            'event' => $event,
            'utilisateur' => $user,
        ]);

        if ($existing) {
            $this->addFlash('info', 'Vous participez déjà à cet événement 🙂');
            return $this->redirectToRoute('patient_event_index');
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
        return $this->redirectToRoute('patient_event_index');
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
            return $this->redirectToRoute('patient_event_index');
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
            return $this->redirectToRoute('patient_event_index');
        }

        $em->remove($existing);
        $em->flush();

        $this->addFlash('success', 'Participation annulée ✅');
        return $this->redirectToRoute('patient_event_index');
    }
}
