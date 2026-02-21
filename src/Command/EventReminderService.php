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
        // ✅ Force timezone (IMPORTANT)
        $tz  = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $tz);

        $events = $this->eventRepository->findEventsToRemind($now);
        $sent = 0;

        foreach ($events as $event) {

            $sentForThisEvent = 0;

            foreach ($event->getParticipations() as $p) {
                $user = $p->getUtilisateur();
                if (!$user || !$user->getEmail()) {
                    continue;
                }

                $prenom = $user->getPrenom() ?? $user->getNom() ?? '';
                $titre  = $event->getTitre() ?? 'Événement';
                $lieu   = $event->getLieu() ?? '—';

                $date = $event->getDateDebut()
                    ? (clone $event->getDateDebut())->setTimezone($tz)->format('d/m/Y H:i')
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
                    ->text("Rappel événement : {$titre}\n\nDate : {$date}\nLieu : {$lieu}\n\nÀ bientôt.");

                try {
                    $this->mailer->send($message);
                    $sent++;
                    $sentForThisEvent++;
                } catch (\Throwable $e) {
                    // ✅ En dev tu verras l'erreur dans les logs Symfony
                    // Tu peux aussi logger $e->getMessage() si tu utilises LoggerInterface
                    continue;
                }
            }

            // ✅ Mark reminder_sent ONLY if at least 1 email sent
            if ($sentForThisEvent > 0) {
                $event->setReminderSent(true);
            }
        }

        $this->em->flush();

        return $sent;
    }
}