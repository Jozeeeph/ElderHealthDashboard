<?php

namespace App\Controller\Api;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use App\Entity\Utilisateur;
use App\Repository\RendezVousRepository;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/calendar', name: 'api_calendar_')]
class CalendarRendezVousApiController extends AbstractController
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService
    ) {
    }

    #[Route('/rendezvous', name: 'rendezvous', methods: ['GET'])]
    public function rendezvousEvents(Request $request): JsonResponse
    {
        $this->requirePersonnelMedical();

        $startRaw = (string) $request->query->get('start', $request->query->get('startStr', ''));
        $endRaw = (string) $request->query->get('end', $request->query->get('endStr', ''));

        $start = $this->parseBoundary($startRaw, true);
        $end = $this->parseBoundary($endRaw, false);

        if ($start === null || $end === null || $end < $start) {
            return $this->json([
                'ok' => false,
                'message' => 'Intervalle de dates invalide. Parametres start/end requis.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->googleCalendarService->isEnabled()) {
            return $this->json([
                'ok' => false,
                'message' => 'Google Calendar n est pas configure. Activez GOOGLE_CALENDAR_ENABLED et renseignez GOOGLE_CALENDAR_ID + GOOGLE_CALENDAR_API_KEY.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $externalEvents = $this->googleCalendarService->fetchEvents($start, $end);
        $response = $this->json($externalEvents);
        $response->headers->set('X-Calendar-Source', 'google');
        return $response;
    }

    #[Route('/availability/check', name: 'availability_check', methods: ['GET'])]
    public function checkAvailability(
        Request $request,
        EntityManagerInterface $em,
        RendezVousRepository $rendezVousRepository
    ): JsonResponse {
        $personnel = $this->resolvePersonnelForAvailability($request, $em);

        $dateRaw = (string) $request->query->get('date', '');
        $heureRaw = (string) $request->query->get('heure', '');
        $typeId = $request->query->getInt('typeId', 0);
        $excludeId = $request->query->getInt('excludeId', 0);

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        $heure = \DateTimeImmutable::createFromFormat('H:i', $heureRaw);
        if (!$date instanceof \DateTimeImmutable || !$heure instanceof \DateTimeImmutable) {
            return $this->json([
                'ok' => false,
                'available' => true,
                'message' => 'Format date/heure invalide. Attendu: date=Y-m-d et heure=H:i.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $duration = $this->resolveDurationMinutes($typeId, $em);
        $hasConflict = $rendezVousRepository->hasPlannedOverlapForPersonnel(
            $personnel,
            $date,
            $heure,
            $duration,
            $excludeId > 0 ? $excludeId : null
        );

        return $this->json([
            'ok' => true,
            'available' => !$hasConflict,
            'durationMinutes' => $duration,
            'message' => $hasConflict ? 'Ce creneau chevauche un rendez-vous deja planifie.' : 'Creneau disponible.',
        ]);
    }

    #[Route('/availability/slots', name: 'availability_slots', methods: ['GET'])]
    public function availabilitySlots(
        Request $request,
        EntityManagerInterface $em,
        RendezVousRepository $rendezVousRepository
    ): JsonResponse {
        $personnel = $this->resolvePersonnelForAvailability($request, $em);

        $dateRaw = (string) $request->query->get('date', '');
        $fromRaw = (string) $request->query->get('from', '08:00');
        $toRaw = (string) $request->query->get('to', '18:00');
        $typeId = $request->query->getInt('typeId', 0);

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if (!$date instanceof \DateTimeImmutable) {
            return $this->json([
                'ok' => false,
                'message' => 'Format de date invalide. Attendu: date=Y-m-d.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dayStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $fromRaw);
        $dayEnd = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $toRaw);
        if (!$dayStart instanceof \DateTimeImmutable || !$dayEnd instanceof \DateTimeImmutable || $dayEnd <= $dayStart) {
            return $this->json([
                'ok' => false,
                'message' => 'Format from/to invalide. Attendu: from=H:i, to=H:i et to > from.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $duration = $this->resolveDurationMinutes($typeId, $em);
        $plannedIntervals = $this->buildPlannedIntervals($rendezVousRepository, $personnel, $date);

        $slots = [];
        for ($cursor = $dayStart; $cursor < $dayEnd; $cursor = $cursor->modify('+' . $duration . ' minutes')) {
            $slotEnd = $cursor->modify('+' . $duration . ' minutes');
            if ($slotEnd > $dayEnd) {
                break;
            }

            $available = !$this->overlapsAny($cursor, $slotEnd, $plannedIntervals);
            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'available' => $available,
            ];
        }

        return $this->json([
            'ok' => true,
            'date' => $date->format('Y-m-d'),
            'durationMinutes' => $duration,
            'slots' => $slots,
        ]);
    }

    private function getAuthenticatedUtilisateur(): Utilisateur
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        return $user;
    }

    private function requirePersonnelMedical(): Utilisateur
    {
        $user = $this->getAuthenticatedUtilisateur();
        $this->denyAccessUnlessGranted('ROLE_PERSONNEL_MEDICAL');

        if (strtoupper((string) $user->getRoleMetier()) !== 'PERSONNEL_MEDICAL') {
            throw $this->createAccessDeniedException('Acces reserve au personnel medical.');
        }

        return $user;
    }

    private function resolvePersonnelForAvailability(Request $request, EntityManagerInterface $em): Utilisateur
    {
        $user = $this->getAuthenticatedUtilisateur();
        $roleMetier = strtoupper((string) $user->getRoleMetier());

        if ($roleMetier === 'PERSONNEL_MEDICAL') {
            return $user;
        }

        if ($roleMetier !== 'PATIENT') {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $personnelId = $request->query->getInt('personnelId', 0);
        if ($personnelId <= 0) {
            return $this->invalidPersonnel();
        }

        $personnel = $em->getRepository(Utilisateur::class)->find($personnelId);
        if (!$personnel instanceof Utilisateur || strtoupper((string) $personnel->getRoleMetier()) !== 'PERSONNEL_MEDICAL') {
            return $this->invalidPersonnel();
        }

        return $personnel;
    }

    private function invalidPersonnel(): never
    {
        throw $this->createNotFoundException('Personnel medical introuvable.');
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
     * @return string[]
     */
    private function parseStatuses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = array_map(
            static fn(string $status): string => strtoupper(trim($status)),
            explode(',', $raw)
        );

        return array_values(array_filter($parts, static fn(string $status): bool => $status !== ''));
    }

    private function resolveDurationMinutes(int $typeId, EntityManagerInterface $em): int
    {
        if ($typeId <= 0) {
            return 45;
        }

        $type = $em->getRepository(TypeRendezVous::class)->find($typeId);
        if (!$type instanceof TypeRendezVous) {
            return 45;
        }

        return RendezVousRepository::durationToMinutes($type->getDuree(), 45);
    }

    /**
     * @return array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function buildPlannedIntervals(
        RendezVousRepository $rendezVousRepository,
        Utilisateur $personnel,
        \DateTimeImmutable $date
    ): array {
        $start = $date->setTime(0, 0, 0);
        $end = $date->setTime(23, 59, 59);

        $items = $rendezVousRepository->findForPersonnelBetween($personnel, $start, $end);
        $intervals = [];

        foreach ($items as $rdv) {
            if (!in_array((string) $rdv->getEtat(), ['PLANIFIE', 'PLANIFIEE', 'EN_COURS'], true)) {
                continue;
            }

            $event = $this->toCalendarEvent($rdv);
            if ($event === null) {
                continue;
            }

            $eventStart = new \DateTimeImmutable((string) $event['start']);
            $eventEnd = new \DateTimeImmutable((string) $event['end']);

            $intervals[] = [
                'start' => $eventStart,
                'end' => $eventEnd,
            ];
        }

        return $intervals;
    }

    /**
     * @param array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $intervals
     */
    private function overlapsAny(\DateTimeImmutable $candidateStart, \DateTimeImmutable $candidateEnd, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($candidateStart < $interval['end'] && $candidateEnd > $interval['start']) {
                return true;
            }
        }

        return false;
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
            'status' => (string) $rdv->getEtat(),
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

