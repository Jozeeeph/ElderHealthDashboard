<?php

namespace App\Controller\FrontOffice;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Event;

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
    public function show(int $id, EventRepository $eventRepository): Response
    {
        // Debug: Check if we're getting the ID
        // dd("ID received: " . $id);
        
        $event = $eventRepository->find($id);
        
        if (!$event) {
            throw $this->createNotFoundException('Événement non trouvé');
        }
        
        if ($event->getStatut() !== 'PUBLIE') {
            throw $this->createNotFoundException('Cet événement n\'est pas publié');
        }

        return $this->render('FrontOffice/events/show.html.twig', [
            'event' => $event
        ]);
    }
}