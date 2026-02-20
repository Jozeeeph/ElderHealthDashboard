<?php
namespace App\Service;

use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;

class RendezVousEtatService
{
    public function __construct(
        private RendezVousRepository $repository,
        private EntityManagerInterface $em,
        private GoogleCalendarSyncService $googleCalendarSyncService
    ) {}

    public function updateEtats(): void
    {
        $now = new \DateTime();

        $rendezVousList = $this->repository->findAll();

        foreach ($rendezVousList as $rdv) {

            if (!$rdv->getDate() || !$rdv->getHeure()) {
                continue;
            }
            if (in_array($rdv->getEtat(), ['EN_ATTENTE', 'ANNULEE', 'REFUSEE'], true)) {
                continue;
            }

            $rdvDateTime = new \DateTime(
                $rdv->getDate()->format('Y-m-d') . ' ' .
                $rdv->getHeure()->format('H:i:s')
            );

            $previousEtat = (string) $rdv->getEtat();
            if ($rdvDateTime > $now) {
                $rdv->setEtat('PLANIFIE');
            } elseif ($rdvDateTime->format('Y-m-d') === $now->format('Y-m-d')) {
                $rdv->setEtat('EN_COURS');
            } else {
                $rdv->setEtat('TERMINE');
            }

            if ($previousEtat !== 'PLANIFIE' && (string) $rdv->getEtat() === 'PLANIFIE' && $this->googleCalendarSyncService->isEnabled()) {
                $this->googleCalendarSyncService->syncPlannedRendezVous($rdv);
            }
        }

        $this->em->flush();
    }
}
