<?php

namespace App\Controller\FrontControllers;

use App\Entity\Utilisateur;
use App\Service\GoogleCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/infermier/calendrier', name: 'front_infermier_calendar_')]
class NurseCalendarController extends AbstractController
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService
    ) {
    }

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
    public function index(): Response
    {
        $this->requirePersonnel();

        if ($this->googleCalendarService->isEnabled()) {
            $googleCalendarUrl = $this->googleCalendarService->getCalendarWebUrl();
            if (is_string($googleCalendarUrl) && $googleCalendarUrl !== '') {
                return $this->redirect($googleCalendarUrl);
            }
        }

        return new Response(
            'Google Calendar n est pas configure. Configurez GOOGLE_CALENDAR_ENABLED, GOOGLE_CALENDAR_ID et GOOGLE_CALENDAR_API_KEY.',
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }
}
