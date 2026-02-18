<?php

namespace App\Controller\FrontControllers;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/infermier/calendrier', name: 'front_infermier_calendar_')]
class NurseCalendarController extends AbstractController
{
    private function requirePersonnel(): Utilisateur
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
        if (strtoupper((string) $user->getRoleMetier()) !== 'PERSONNEL_MEDICAL') {
            throw $this->createAccessDeniedException('Acces reserve au personnel medical.');
        }

        return $user;
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->requirePersonnel();
        $notifications = $rendezVousRepository->findPendingForPersonnel($user, 6);

        return $this->render('FrontOffice/infermier/calendar/index.html.twig', [
            'nurseName' => $user->getPrenom(),
            'notifications' => $notifications,
            'eventsFeedUrl' => $this->generateUrl('front_infermier_calendar_events'),
        ]);
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request, RendezVousRepository $rendezVousRepository): JsonResponse
    {
        $user = $this->requirePersonnel();
        $start = $this->parseBoundary((string) $request->query->get('start', ''), true);
        $end = $this->parseBoundary((string) $request->query->get('end', ''), false);

        if ($start === null || $end === null || $end < $start) {
            return $this->json(['error' => 'Intervalle invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $rendezVous = $rendezVousRepository->findForPersonnelBetween($user, $start, $end);
        $events = [];
        foreach ($rendezVous as $rdv) {
            if (!in_array((string) $rdv->getEtat(), ['PLANIFIE', 'PLANIFIEE'], true)) {
                continue;
            }
            $event = $this->toCalendarEvent($rdv);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $this->json($events);
    }

    private function parseBoundary(string $rawDate, bool $isStart): ?\DateTimeImmutable
    {
        if ($rawDate === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($rawDate);
        } catch (\Throwable) {
            return null;
        }

        return $isStart ? $date->setTime(0, 0, 0) : $date->setTime(23, 59, 59);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toCalendarEvent(RendezVous $rdv): ?array
    {
        if ($rdv->getDate() === null || $rdv->getHeure() === null) {
            return null;
        }

        $startAt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $rdv->getDate()->format('Y-m-d') . ' ' . $rdv->getHeure()->format('H:i:s')
        );
        if (!$startAt instanceof \DateTimeImmutable) {
            return null;
        }

        $patientName = trim((string) ($rdv->getPatient()?->getPrenom() . ' ' . $rdv->getPatient()?->getNom()));
        $careType = (string) ($rdv->getTypeRendezVous()?->getType() ?? 'Soin');
        $duration = RendezVousRepository::durationToMinutes($rdv->getTypeRendezVous()?->getDuree(), 45);

        return [
            'id' => (string) $rdv->getId(),
            'title' => sprintf('%s - %s', $patientName !== '' ? $patientName : 'Patient', $careType),
            'start' => $startAt->format(\DateTimeInterface::ATOM),
            'end' => $startAt->modify('+' . $duration . ' minutes')->format(\DateTimeInterface::ATOM),
            'backgroundColor' => '#dbeafe',
            'borderColor' => '#2563eb',
            'textColor' => '#1e3a8a',
            'extendedProps' => [
                'patientName' => $patientName !== '' ? $patientName : 'Patient inconnu',
                'time' => $rdv->getHeure()->format('H:i'),
                'careType' => $careType,
                'duration' => $duration,
                'status' => (string) $rdv->getEtat(),
                'location' => (string) ($rdv->getLieu() ?? '-'),
                'isPaid' => $rdv->isPaid(),
            ],
        ];
    }
}
