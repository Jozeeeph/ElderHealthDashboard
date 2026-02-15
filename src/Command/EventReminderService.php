<?php

namespace App\Service;

use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EventReminderService
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private string $fromEmail
    ) {}

    public function checkAndSendReminders(): int
    {
        $now = new \DateTimeImmutable('now');
        $events = $this->eventRepository->findEventsToRemind($now);
        $sent = 0;

        foreach ($events as $event) {

            foreach ($event->getParticipations() as $p) {
                $user = $p->getUtilisateur();
                if (!$user || !$user->getEmail()) continue;

                $prenom = $user->getPrenom() ?? $user->getNom() ?? '';
                $titre  = $event->getTitre() ?? 'Événement';
                $lieu   = $event->getLieu() ?? '—';
                $date   = $event->getDateDebut()
                    ? $event->getDateDebut()->format('d/m/Y H:i')
                    : '—';

                $message = (new Email())
                    ->from($this->fromEmail)
                    ->to($user->getEmail())
                    ->subject('⏰ Rappel événement : ' . $titre)
                    ->html("
                        <h2>⏰ Rappel : {$titre}</h2>
                        <p>Bonjour {$prenom},</p>
                        <p>Votre événement commence demain.</p>
                        <ul>
                            <li><strong>Date :</strong> {$date}</li>
                            <li><strong>Lieu :</strong> {$lieu}</li>
                        </ul>
                        <p>À bientôt 😊</p>
                    ")
                    ->text("
                        Rappel événement : {$titre}

                        Date : {$date}
                        Lieu : {$lieu}

                        À bientôt.
                    ");

                $this->mailer->send($message);
                $sent++;
            }

            $event->setReminderSent(true);
            $this->em->persist($event);
        }

        $this->em->flush();

        return $sent;
    }
}
