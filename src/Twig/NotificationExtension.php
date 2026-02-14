<?php

namespace App\Twig;

use App\Entity\Utilisateur;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('header_notifications', [$this, 'getHeaderNotifications']),
        ];
    }

    /**
     * @return array{count:int,hasNew:bool,items:array<int,string>}
     */
    public function getHeaderNotifications(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return ['count' => 0, 'hasNew' => false, 'items' => []];
        }

        if ($this->security->isGranted('ROLE_PERSONNEL_MEDICAL')) {
            $count = $this->rendezVousRepository->countPendingForPersonnel($user);
            $items = [];
            foreach ($this->rendezVousRepository->findPendingForPersonnel($user, 6) as $rdv) {
                $patient = $rdv->getPatient();
                $type = $rdv->getTypeRendezVous();
                $items[] = sprintf(
                    'Nouvelle demande: %s - %s (%s %s)',
                    $patient ? trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')) : 'Patient',
                    $type ? ($type->getType() ?? 'Rendez-vous') : 'Rendez-vous',
                    $rdv->getDate() ? $rdv->getDate()->format('d/m/Y') : '-',
                    $rdv->getHeure() ? $rdv->getHeure()->format('H:i') : ''
                );
            }

            return [
                'count' => $count,
                'hasNew' => $count > 0,
                'items' => $items,
            ];
        }

        if ($this->security->isGranted('ROLE_PATIENT')) {
            $request = $this->requestStack->getCurrentRequest();
            $session = $request?->getSession();
            $seenIds = $session?->get('patient_rdv_seen_notification_ids', []);
            if (!is_array($seenIds)) {
                $seenIds = [];
            }
            $seenIds = array_map('intval', $seenIds);

            $all = $this->rendezVousRepository->findStatusNotificationsForPatient($user, 20);
            $unseen = array_values(array_filter($all, static function ($rdv) use ($seenIds): bool {
                return !in_array((int) $rdv->getId(), $seenIds, true);
            }));

            $count = count($unseen);
            $items = [];
            foreach (array_slice($unseen, 0, 6) as $rdv) {
                $type = $rdv->getTypeRendezVous();
                $etat = (string) $rdv->getEtat();
                $action = in_array($etat, ['PLANIFIE', 'PLANIFIEE'], true) ? 'Demande acceptee' : 'Demande refusee';

                $items[] = sprintf(
                    '%s: %s (%s %s)',
                    $action,
                    $type ? ($type->getType() ?? 'Rendez-vous') : 'Rendez-vous',
                    $rdv->getDate() ? $rdv->getDate()->format('d/m/Y') : '-',
                    $rdv->getHeure() ? $rdv->getHeure()->format('H:i') : ''
                );
            }

            return [
                'count' => $count,
                'hasNew' => $count > 0,
                'items' => $items,
            ];
        }

        return ['count' => 0, 'hasNew' => false, 'items' => []];
    }
}
