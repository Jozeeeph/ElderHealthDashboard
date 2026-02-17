<?php

namespace App\Twig;

use App\Entity\Prescription;
use App\Entity\Utilisateur;
use App\Repository\PrescriptionDoseAckRepository;
use App\Repository\PrescriptionRepository;
use App\Repository\RendezVousRepository;
use App\Service\PrescriptionReminderScheduler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly PrescriptionRepository $prescriptionRepository,
        private readonly PrescriptionDoseAckRepository $prescriptionDoseAckRepository,
        private readonly PrescriptionReminderScheduler $prescriptionReminderScheduler,
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
     * @return array{count:int,hasNew:bool,items:array<int,mixed>,toast_items:array<int,mixed>,canClearRdv:bool}
     */
    public function getHeaderNotifications(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return ['count' => 0, 'hasNew' => false, 'items' => [], 'toast_items' => [], 'canClearRdv' => false];
        }

        if ($this->security->isGranted('ROLE_PERSONNEL_MEDICAL')) {
            $request = $this->requestStack->getCurrentRequest();
            $session = $request?->getSession();

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

            $doneItems = [];
            $recentDone = $this->prescriptionDoseAckRepository->findRecentDoneForPersonnel($user, 10);
            foreach ($recentDone as $ack) {
                $prescription = $ack->getPrescription();
                $consultation = $prescription?->getConsultation();
                $patient = $consultation?->getPatient();
                $patientName = trim((string) (($patient?->getPrenom() ?? '') . ' ' . ($patient?->getNom() ?? '')));
                if ($patientName === '') {
                    $patientName = 'Patient';
                }

                $doneAt = $ack->getDoneAt();
                $ackId = $ack->getId();
                $doneItems[] = [
                    'type' => 'patient_dose_done',
                    'ref' => $ackId !== null ? ('ack:' . $ackId) : null,
                    'message' => sprintf(
                        '%s a confirme une prise (%s).',
                        $patientName,
                        $doneAt ? $doneAt->format('H:i') : 'maintenant'
                    ),
                ];
            }

            $endingItems = [];
            $today = new \DateTimeImmutable('today');
            foreach ($this->prescriptionRepository->findEndingTodayForPersonnel($user, $today) as $prescription) {
                $consultation = $prescription->getConsultation();
                $patient = $consultation?->getPatient();
                $patientName = trim((string) (($patient?->getPrenom() ?? '') . ' ' . ($patient?->getNom() ?? '')));
                if ($patientName === '') {
                    $patientName = 'Patient';
                }

                $endingItems[] = [
                    'type' => 'treatment_ended',
                    'ref' => 'ending:' . (string) ($prescription->getIdPrescription() ?? '') . ':' . $today->format('Y-m-d'),
                    'message' => sprintf(
                        'Fin de traitement aujourd hui pour %s.',
                        $patientName
                    ),
                ];
            }

            $liveItems = array_merge($endingItems, $doneItems, $items);
            $displayItems = $liveItems;
            $toastItems = [];

            // Save nurse notifications in session stock to keep them after refresh.
            if ($session !== null) {
                $stock = $session->get('personnel_notification_stock', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                $dismissed = $session->get('personnel_notification_dismissed_keys', []);
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                $dismissedMap = array_fill_keys(array_map('strval', $dismissed), true);
                $shown = $session->get('personnel_notification_toast_seen_keys', []);
                if (!is_array($shown)) {
                    $shown = [];
                }
                $shownMap = array_fill_keys(array_map('strval', $shown), true);

                $knownKeys = [];
                foreach ($stock as $idx => $entry) {
                    if (!is_array($entry)) {
                        $entry = $this->normalizeNotificationItem($entry);
                    }
                    if (!isset($entry['_key'])) {
                        $entry['_key'] = $this->buildNotificationKey($this->normalizeNotificationItem($entry));
                    }
                    if (isset($dismissedMap[(string) $entry['_key']])) {
                        continue;
                    }
                    $stock[$idx] = $entry;
                    $knownKeys[(string) $entry['_key']] = true;
                }
                $stock = array_values(array_filter($stock, static fn ($x) => is_array($x)));

                $nowIso = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                foreach ($liveItems as $entry) {
                    $payload = $this->normalizeNotificationItem($entry);
                    $key = $this->buildNotificationKey($payload);
                    if (isset($dismissedMap[$key])) {
                        continue;
                    }
                    if (!isset($dismissedMap[$key]) && !isset($shownMap[$key])) {
                        $toastItems[] = $payload;
                        $shownMap[$key] = true;
                    }
                    if (isset($knownKeys[$key])) {
                        continue;
                    }

                    $payload['_key'] = $key;
                    $payload['_stored_at'] = $nowIso;
                    array_unshift($stock, $payload);
                    $knownKeys[$key] = true;
                }

                $stock = array_slice($stock, 0, 80);
                $session->set('personnel_notification_stock', $stock);
                $session->set('personnel_notification_toast_seen_keys', array_slice(array_keys($shownMap), 0, 300));
                $displayItems = $stock;
            }

            $allCount = count($displayItems);

            return [
                'count' => $allCount,
                'hasNew' => count($liveItems) > 0,
                'items' => $displayItems,
                'toast_items' => $toastItems,
                'canClearRdv' => false,
            ];
        }

        if ($this->security->isGranted('ROLE_PATIENT')) {
            $request = $this->requestStack->getCurrentRequest();
            $session = $request?->getSession();
            $route = (string) ($request?->attributes->get('_route') ?? '');
            $isPatientRendezVousRoute = str_starts_with($route, 'patient_rendezvous_');

            $seenIds = $session?->get('patient_rdv_seen_notification_ids', []);
            if (!is_array($seenIds)) {
                $seenIds = [];
            }
            $seenIds = array_map('intval', $seenIds);

            if ($isPatientRendezVousRoute && $session !== null) {
                $currentNotifIds = $this->rendezVousRepository->findStatusNotificationIdsForPatient($user);
                $seenIds = array_values(array_unique(array_map('intval', array_merge($seenIds, $currentNotifIds))));
                $session->set('patient_rdv_seen_notification_ids', $seenIds);
            }

            $all = $this->rendezVousRepository->findStatusNotificationsForPatient($user, 20);
            $unseen = array_values(array_filter($all, static function ($rdv) use ($seenIds): bool {
                return !in_array((int) $rdv->getId(), $seenIds, true);
            }));

            $items = [];
            foreach (array_slice($unseen, 0, 6) as $rdv) {
                $type = $rdv->getTypeRendezVous();
                $etat = (string) $rdv->getEtat();
                $action = in_array($etat, ['PLANIFIE', 'PLANIFIEE'], true) ? 'Demande acceptee' : 'Demande refusee';

                $items[] = [
                    'type' => 'rdv_status',
                    'message' => sprintf(
                        '%s: %s (%s %s)',
                        $action,
                        $type ? ($type->getType() ?? 'Rendez-vous') : 'Rendez-vous',
                        $rdv->getDate() ? $rdv->getDate()->format('d/m/Y') : '-',
                        $rdv->getHeure() ? $rdv->getHeure()->format('H:i') : ''
                    ),
                ];
            }

            $medicationReminders = $this->buildMedicationReminders($user);
            $endingItems = [];
            $today = new \DateTimeImmutable('today');
            foreach ($this->prescriptionRepository->findEndingTodayForPatient($user, $today) as $prescription) {
                $medicament = $this->extractMedicationName((string) $prescription->getMedicaments());
                $endingItems[] = [
                    'type' => 'treatment_ended',
                    'message' => sprintf(
                        'Votre traitement (%s) se termine aujourd hui.',
                        $medicament
                    ),
                ];
            }

            $allItems = array_merge($endingItems, $medicationReminders, $items);
            $displayItems = $allItems;
            $toastItems = [];

            // Save patient notifications in a session stock (history in bell icon).
            if ($session !== null) {
                $stock = $session->get('patient_notification_stock', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                $dismissed = $session->get('patient_notification_dismissed_keys', []);
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                $dismissedMap = array_fill_keys(array_map('strval', $dismissed), true);
                $shown = $session->get('patient_notification_toast_seen_keys', []);
                if (!is_array($shown)) {
                    $shown = [];
                }
                $shownMap = array_fill_keys(array_map('strval', $shown), true);

                $knownKeys = [];
                foreach ($stock as $idx => $entry) {
                    if (!is_array($entry)) {
                        $entry = $this->normalizeNotificationItem($entry);
                    }
                    if (!isset($entry['_key'])) {
                        $entry['_key'] = $this->buildNotificationKey($this->normalizeNotificationItem($entry));
                    }
                    if (isset($dismissedMap[(string) $entry['_key']])) {
                        continue;
                    }
                    $stock[$idx] = $entry;
                    $knownKeys[(string) $entry['_key']] = true;
                }
                $stock = array_values(array_filter($stock, static fn ($x) => is_array($x)));

                $nowIso = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                foreach ($allItems as $entry) {
                    $payload = $this->normalizeNotificationItem($entry);
                    $key = $this->buildNotificationKey($payload);
                    if (isset($dismissedMap[$key])) {
                        continue;
                    }
                    if (!isset($dismissedMap[$key]) && !isset($shownMap[$key])) {
                        $toastItems[] = $payload;
                        $shownMap[$key] = true;
                    }
                    if (isset($knownKeys[$key])) {
                        continue;
                    }

                    $payload['_key'] = $key;
                    $payload['_stored_at'] = $nowIso;
                    array_unshift($stock, $payload);
                    $knownKeys[$key] = true;
                }

                $stock = array_slice($stock, 0, 80);
                $session->set('patient_notification_stock', $stock);
                $session->set('patient_notification_toast_seen_keys', array_slice(array_keys($shownMap), 0, 300));
                $displayItems = $stock;
            }

            $count = count($displayItems);

            return [
                'count' => $count,
                'hasNew' => count($allItems) > 0,
                'items' => $displayItems,
                'toast_items' => $toastItems,
                'canClearRdv' => false,
            ];
        }

        return ['count' => 0, 'hasNew' => false, 'items' => [], 'toast_items' => [], 'canClearRdv' => false];
    }

    /**
     * @return list<array{type:string,message:string,overdue:bool,prescription_id:int,scheduled_at:string,token_id:string}>
     */
    private function buildMedicationReminders(Utilisateur $patient): array
    {
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();
        $prescriptions = $this->prescriptionRepository->findActiveForPatient($patient, $today);
        if ($prescriptions === []) {
            return [];
        }

        $prescriptionIds = [];
        foreach ($prescriptions as $prescription) {
            $id = $prescription->getIdPrescription();
            if ($id !== null) {
                $prescriptionIds[] = $id;
            }
        }

        $doneKeys = $this->prescriptionDoseAckRepository->findDoneSlotKeys(
            $prescriptionIds,
            $today->setTime(0, 0, 0),
            $today->setTime(23, 59, 59)
        );

        $reminders = [];
        foreach ($prescriptions as $prescription) {
            $prescriptionId = $prescription->getIdPrescription();
            if ($prescriptionId === null) {
                continue;
            }

            $slots = $this->prescriptionReminderScheduler->buildSlotsForDate($prescription, $today);
            foreach ($slots as $slot) {
                if ($now < $slot) {
                    continue;
                }

                $slotKey = $prescriptionId . '|' . $slot->format('Y-m-d H:i');
                if (isset($doneKeys[$slotKey])) {
                    continue;
                }

                $overdue = $now > $slot->modify('+30 minutes');
                $medicament = $this->extractMedicationName((string) $prescription->getMedicaments());
                $message = sprintf(
                    'Rappel medicament (%s): prenez votre dose (%s) a %s.',
                    $medicament,
                    (string) $prescription->getDosage(),
                    $slot->format('H:i')
                );

                $slotString = $slot->format('Y-m-d H:i');
                $reminders[] = [
                    'type' => 'prescription_reminder',
                    'message' => $message,
                    'overdue' => $overdue,
                    'prescription_id' => $prescriptionId,
                    'scheduled_at' => $slotString,
                    'token_id' => 'patient_med_reminder_' . $prescriptionId . '_' . $slotString,
                    '_sort' => $slot->getTimestamp(),
                ];
            }
        }

        usort($reminders, static function (array $a, array $b): int {
            return ($a['_sort'] ?? 0) <=> ($b['_sort'] ?? 0);
        });

        foreach ($reminders as &$reminder) {
            unset($reminder['_sort']);
        }
        unset($reminder);

        return array_slice($reminders, 0, 8);
    }

    private function extractMedicationName(string $medicaments): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $medicaments));
        if ($value === '') {
            return 'traitement';
        }

        $parts = preg_split('/\n|,|;/', $value);
        $first = trim((string) ($parts[0] ?? ''));
        if ($first === '') {
            return 'traitement';
        }

        return mb_substr($first, 0, 60);
    }

    private function normalizeNotificationItem(mixed $entry): array
    {
        if (is_array($entry)) {
            $item = [
                'type' => (string) ($entry['type'] ?? 'info'),
                'message' => (string) ($entry['message'] ?? ''),
            ];

            foreach (['overdue', 'prescription_id', 'scheduled_at', 'token_id', 'ref'] as $field) {
                if (array_key_exists($field, $entry)) {
                    $item[$field] = $entry[$field];
                }
            }

            return $item;
        }

        return [
            'type' => 'info',
            'message' => (string) $entry,
        ];
    }

    private function buildNotificationKey(array $item): string
    {
        return hash(
            'sha256',
            (string) ($item['type'] ?? '') . '|' .
            (string) ($item['message'] ?? '') . '|' .
            (string) ($item['ref'] ?? '') . '|' .
            (string) ($item['prescription_id'] ?? '') . '|' .
            (string) ($item['scheduled_at'] ?? '')
        );
    }
}
