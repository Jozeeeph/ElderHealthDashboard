<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Prescription;
use App\Entity\RapportMedical;
use App\Enum\Role;
use App\Form\PatientProfileType;
use App\Repository\ConsultationRepository;
use App\Repository\PrescriptionDoseAckRepository;
use App\Repository\PrescriptionRepository;
use App\Repository\UtilisateurRepository;
use App\Service\PrescriptionReminderScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PatientController extends AbstractController
{

    #[Route('/patient/home', name: 'app_patient_interfce')]
    public function patientDashboard(): Response
    {
        return $this->render('FrontOffice/patient/index.html.twig');
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function patientConsultations(ConsultationRepository $consultationRepository, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('medication_error', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultations = $consultationRepository->findByPatient($patient);

        return $this->render('FrontOffice/patient/consultations.html.twig', [
            'consultations' => $consultations,
            'patient' => $patient,
        ]);
    }

    #[Route('/patient/profil', name: 'patient_profile', methods: ['GET', 'POST'])]
    public function patientProfile(Request $request, EntityManagerInterface $em, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $form = $this->createForm(PatientProfileType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            // $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('patient_profile');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('FrontOffice/patient/_profile_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('FrontOffice/patient/profile.html.twig', [
            'form' => $form->createView(),
            'patient' => $patient,
        ]);
    }

    #[Route('/patient/prescription/{id}', name: 'patient_prescription_show', methods: ['GET'])]
    public function patientPrescriptionShow(
        Prescription $prescription,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        HttpClientInterface $httpClient
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PATIENT');

        $allowedLocales = ['fr', 'ar', 'en'];
        $uiLocale = strtolower((string) $request->query->get('lang', $request->getLocale()));
        if (!in_array($uiLocale, $allowedLocales, true)) {
            $uiLocale = 'en';
        }
        $request->setLocale($uiLocale);

        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultation = $prescription->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createNotFoundException('Prescription introuvable.');
        }

        $translatedPrescription = $this->translatePrescriptionFields($prescription, $uiLocale, $httpClient);

        return $this->render('FrontOffice/patient/prescription_show.html.twig', [
            'prescription' => $prescription,
            'patient' => $patient,
            'ui_locale' => $uiLocale,
            'translated_prescription' => $translatedPrescription,
        ]);
    }

    #[Route('/patient/rapport/{id}', name: 'patient_rapport_show', methods: ['GET'])]
    public function patientRapportShow(RapportMedical $rapport, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultation = $rapport->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createNotFoundException('Rapport introuvable.');
        }

        return $this->render('FrontOffice/patient/rapport_show.html.twig', [
            'rapport' => $rapport,
            'patient' => $patient,
        ]);
    }

    #[Route('/patient/rapport/{id}/pdf', name: 'patient_rapport_pdf', methods: ['GET'])]
    public function patientRapportPdf(RapportMedical $rapport, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultation = $rapport->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createNotFoundException('Rapport introuvable.');
        }

        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('patient_rapport_show', ['id' => $rapport->getIdRapport()]);
        }

        $logoDataUri = null;
        if (function_exists('imagecreatefrompng')) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $logoPath = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png';
            if (is_file($logoPath)) {
                $data = base64_encode((string) file_get_contents($logoPath));
                $logoDataUri = 'data:image/png;base64,' . $data;
            }
        }

        $attachment = null;
        if ($rapport->getFichierPath()) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $publicPath = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $rapport->getFichierPath();
            if (is_file($publicPath)) {
                $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $attachment = [
                    'is_image' => $isImage,
                    'name' => basename($publicPath),
                ];

                if ($isImage) {
                    $mime = match ($ext) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'application/octet-stream',
                    };
                    $data = base64_encode((string) file_get_contents($publicPath));
                    $attachment['data_uri'] = 'data:' . $mime . ';base64,' . $data;
                }
            } else {
                $attachment = [
                    'missing' => true,
                    'name' => basename($rapport->getFichierPath()),
                ];
            }
        }

        $html = $this->renderView('BackOffice/rapport_medical/pdf.html.twig', [
            'rapport' => $rapport,
            'attachment' => $attachment,
            'logo_data_uri' => $logoDataUri,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapport-medical-' . $rapport->getIdRapport() . '.pdf"',
        ]);
    }

    #[Route('/patient/prescription/{id}/pdf', name: 'patient_prescription_pdf', methods: ['GET'])]
    public function patientPrescriptionPdf(Prescription $prescription, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultation = $prescription->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createNotFoundException('Prescription introuvable.');
        }

        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('patient_prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        $logoDataUri = null;
        if (function_exists('imagecreatefrompng')) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $logoPath = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png';
            if (is_file($logoPath)) {
                $data = base64_encode((string) file_get_contents($logoPath));
                $logoDataUri = 'data:image/png;base64,' . $data;
            }
        }

        $html = $this->renderView('BackOffice/prescription/pdf.html.twig', [
            'prescription' => $prescription,
            'logo_data_uri' => $logoDataUri,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename=prescription-' . $prescription->getIdPrescription() . '.pdf'
        );

        return $response;
    }

    #[Route('/patient/prescription/{id}/tts', name: 'patient_prescription_tts', methods: ['GET'])]
    public function patientPrescriptionTts(
        Prescription $prescription,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        HttpClientInterface $httpClient,
        TranslatorInterface $translator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PATIENT');

        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $consultation = $prescription->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            return new JsonResponse(['error' => 'Prescription introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $lang = strtolower((string) $request->query->get('lang', 'en-US'));
        $voiceLang = match (true) {
            str_starts_with($lang, 'ar-tn') => 'ar-TN',
            str_starts_with($lang, 'ar') => 'ar-SA',
            str_starts_with($lang, 'fr') => 'fr-FR',
            default => 'en-US',
        };
        $uiLocale = match (true) {
            str_starts_with($lang, 'ar') => 'ar',
            str_starts_with($lang, 'fr') => 'fr',
            default => 'en',
        };
        $speechLocale = str_starts_with($lang, 'ar-tn') ? 'ar_tn' : $uiLocale;

        $translatedPrescription = $this->translatePrescriptionFields($prescription, $uiLocale, $httpClient);
        $speechText = $this->buildPrescriptionSpeechText($prescription, $translator, $speechLocale, $translatedPrescription);

        try {
            $ttsResponse = $httpClient->request('GET', 'https://translate.google.com/translate_tts', [
                'query' => [
                    'ie' => 'UTF-8',
                    'client' => 'tw-ob',
                    'tl' => $voiceLang,
                    'q' => $speechText,
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                ],
                'timeout' => 15,
            ]);

            $audioContent = $ttsResponse->getContent();
        } catch (\Throwable) {
            if ($voiceLang === 'ar-TN') {
                try {
                    $ttsResponse = $httpClient->request('GET', 'https://translate.google.com/translate_tts', [
                        'query' => [
                            'ie' => 'UTF-8',
                            'client' => 'tw-ob',
                            'tl' => 'ar-SA',
                            'q' => $speechText,
                        ],
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                        ],
                        'timeout' => 15,
                    ]);
                    $audioContent = $ttsResponse->getContent();
                } catch (\Throwable) {
                    return new JsonResponse(
                        ['error' => 'Service TTS indisponible pour le moment.'],
                        Response::HTTP_SERVICE_UNAVAILABLE
                    );
                }
            } else {
            return new JsonResponse(
                ['error' => 'Service TTS indisponible pour le moment.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
            }
        }

        return new Response($audioContent, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    #[Route('/patient/prescription/reminder/done', name: 'patient_prescription_reminder_done', methods: ['POST'])]
    public function markPrescriptionReminderDone(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        PrescriptionRepository $prescriptionRepository,
        PrescriptionDoseAckRepository $prescriptionDoseAckRepository,
        PrescriptionReminderScheduler $prescriptionReminderScheduler,
        EntityManagerInterface $em
    ): Response {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $prescriptionId = (int) $request->request->get('prescription_id');
        $scheduledAtRaw = trim((string) $request->request->get('scheduled_at'));
        $tokenId = 'patient_med_reminder_' . $prescriptionId . '_' . $scheduledAtRaw;
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $prescription = $prescriptionRepository->find($prescriptionId);
        if (!$prescription instanceof Prescription) {
            throw $this->createNotFoundException('Prescription introuvable.');
        }

        $consultation = $prescription->getConsultation();
        if (!$consultation || $consultation->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createNotFoundException('Prescription introuvable.');
        }

        $scheduledAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $scheduledAtRaw);
        if (!$scheduledAt) {
            $this->addFlash('medication_error', 'Horaire de rappel invalide.');
            return $this->redirectToRoute('patient_consultations');
        }

        $slots = $prescriptionReminderScheduler->buildSlotsForDate($prescription, $scheduledAt->setTime(0, 0));
        $validSlot = false;
        foreach ($slots as $slot) {
            if ($slot->format('Y-m-d H:i') === $scheduledAt->format('Y-m-d H:i')) {
                $validSlot = true;
                break;
            }
        }

        if (!$validSlot) {
            $this->addFlash('medication_error', 'Ce rappel ne correspond pas a votre prescription.');
            return $this->redirectToRoute('patient_consultations');
        }

        $prescriptionDoseAckRepository->markDone($prescription, $scheduledAt);
        try {
            $em->flush();
        } catch (\Throwable $e) {
            if (str_contains((string) $e->getMessage(), 'prescription_dose_ack')) {
                $this->addFlash('medication_error', 'Les rappels ne sont pas initialises en base. Lancez les migrations Doctrine.');
                return $this->redirectToRoute('patient_consultations');
            }
            throw $e;
        }

        $this->addFlash('medication_success', 'Dose confirmee. Le rappel est masque.');

        return $this->redirectToRoute('patient_consultations');
    }





    #[Route('/patient/{id}/upload-dossier', name: 'patient_upload_dossier', methods: ['POST'])]
    public function uploadDossier(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $patient = $em->getRepository(Utilisateur::class)->find($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient introuvable'], 404);
        }
        if ($patient->getRole() !== Role::PATIENT) {
            return $this->json(['message' => 'Utilisateur non patient'], 400);
        }

        $file = $request->files->get('dossierMedical'); // name="dossierMedical"
        if (!$file) {
            return $this->json(['message' => 'Aucun fichier envoyé'], 400);
        }

        // Vérif PDF
        if ($file->getMimeType() !== 'application/pdf') {
            return $this->json(['message' => 'Le fichier doit être un PDF'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dossiers';
        $newFilename = 'dossier_' . $patient->getId() . '_' . uniqid() . '.pdf';

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['message' => 'Erreur upload fichier'], 500);
        }

        // On stocke juste le chemin relatif
        $patient->setDossierMedicalPath('/uploads/dossiers/' . $newFilename);
        $em->flush();

        return $this->json(['message' => 'Dossier médical uploadé', 'path' => $patient->getDossierMedicalPath()]);
    }

    #[Route('/notifications/clear-all', name: 'app_notifications_clear_all', methods: ['POST'])]
    public function clearAllNotifications(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('clear_all_notifications', (string) $request->request->get('_token'))) {
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_public_site'));
        }

        $session = $request->getSession();
        if ($session) {
            if ($this->isGranted('ROLE_PERSONNEL_MEDICAL')) {
                $stock = $session->get('personnel_notification_stock', []);
                $dismissed = $session->get('personnel_notification_dismissed_keys', []);
                $seen = $session->get('personnel_notification_toast_seen_keys', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                if (!is_array($seen)) {
                    $seen = [];
                }

                foreach ($stock as $entry) {
                    $key = $this->extractNotificationKey($entry);
                    if ($key === null) {
                        continue;
                    }
                    $dismissed[] = $key;
                    $seen[] = $key;
                }

                $session->set('personnel_notification_dismissed_keys', array_values(array_unique($dismissed)));
                $session->set('personnel_notification_toast_seen_keys', array_values(array_unique($seen)));
                $session->remove('personnel_notification_stock');
                $session->remove('personnel_med_ack_seen_ids');
            } elseif ($this->isGranted('ROLE_PATIENT')) {
                $stock = $session->get('patient_notification_stock', []);
                $dismissed = $session->get('patient_notification_dismissed_keys', []);
                $seen = $session->get('patient_notification_toast_seen_keys', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                if (!is_array($seen)) {
                    $seen = [];
                }

                foreach ($stock as $entry) {
                    $key = $this->extractNotificationKey($entry);
                    if ($key === null) {
                        continue;
                    }
                    $dismissed[] = $key;
                    $seen[] = $key;
                }

                $session->set('patient_notification_dismissed_keys', array_values(array_unique($dismissed)));
                $session->set('patient_notification_toast_seen_keys', array_values(array_unique($seen)));
                $session->remove('patient_notification_stock');
                $session->remove('patient_rdv_seen_notification_ids');
            }
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_public_site'));
    }

    #[Route('/notifications/clear-one', name: 'app_notifications_clear_one', methods: ['POST'])]
    public function clearOneNotification(Request $request): Response
    {
        $key = trim((string) $request->request->get('key'));
        $tokenId = 'clear_one_notification_' . $key;
        if ($key === '' || !$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_public_site'));
        }

        $session = $request->getSession();
        if ($session) {
            if ($this->isGranted('ROLE_PERSONNEL_MEDICAL')) {
                $stock = $session->get('personnel_notification_stock', []);
                $dismissed = $session->get('personnel_notification_dismissed_keys', []);
                $seen = $session->get('personnel_notification_toast_seen_keys', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                if (!is_array($seen)) {
                    $seen = [];
                }

                $stock = array_values(array_filter($stock, static function ($entry) use ($key): bool {
                    return !(is_array($entry) && (($entry['_key'] ?? null) === $key));
                }));
                $dismissed[] = $key;
                $seen[] = $key;

                $session->set('personnel_notification_stock', $stock);
                $session->set('personnel_notification_dismissed_keys', array_values(array_unique($dismissed)));
                $session->set('personnel_notification_toast_seen_keys', array_values(array_unique($seen)));
            } elseif ($this->isGranted('ROLE_PATIENT')) {
                $stock = $session->get('patient_notification_stock', []);
                $dismissed = $session->get('patient_notification_dismissed_keys', []);
                $seen = $session->get('patient_notification_toast_seen_keys', []);
                if (!is_array($stock)) {
                    $stock = [];
                }
                if (!is_array($dismissed)) {
                    $dismissed = [];
                }
                if (!is_array($seen)) {
                    $seen = [];
                }

                $stock = array_values(array_filter($stock, static function ($entry) use ($key): bool {
                    return !(is_array($entry) && (($entry['_key'] ?? null) === $key));
                }));
                $dismissed[] = $key;
                $seen[] = $key;

                $session->set('patient_notification_stock', $stock);
                $session->set('patient_notification_dismissed_keys', array_values(array_unique($dismissed)));
                $session->set('patient_notification_toast_seen_keys', array_values(array_unique($seen)));
            }
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_public_site'));
    }

    private function extractNotificationKey(mixed $entry): ?string
    {
        if (is_array($entry)) {
            if (isset($entry['_key']) && is_string($entry['_key']) && $entry['_key'] !== '') {
                return $entry['_key'];
            }

            $type = (string) ($entry['type'] ?? 'info');
            $message = (string) ($entry['message'] ?? '');
            $ref = (string) ($entry['ref'] ?? '');
            $prescriptionId = (string) ($entry['prescription_id'] ?? '');
            $scheduledAt = (string) ($entry['scheduled_at'] ?? '');

            return hash('sha256', $type . '|' . $message . '|' . $ref . '|' . $prescriptionId . '|' . $scheduledAt);
        }

        if (is_string($entry) && $entry !== '') {
            return hash('sha256', 'info|' . $entry . '|||');
        }

        return null;
    }

    private function resolveCurrentPatient(UtilisateurRepository $utilisateurRepository): ?Utilisateur
    {
        $user = $this->getUser();
        if ($user instanceof Utilisateur) {
            return $user;
        }

        if ($user instanceof \App\Entity\User) {
            $email = $user->getEmail();
            if ($email) {
                return $utilisateurRepository->findOneBy(['email' => $email]);
            }
        }

        return null;
    }

    private function buildPrescriptionSpeechText(
        Prescription $prescription,
        TranslatorInterface $translator,
        string $locale,
        array $translatedFields = []
    ): string {
        $periodStart = $prescription->getDateDebut()?->format('d/m/Y') ?? '-';
        $periodEnd = $prescription->getDateFin()?->format('d/m/Y') ?? '-';
        $frequence = (string) ($translatedFields['frequence'] ?? $prescription->getFrequence() ?? '-');
        $dosage = (string) ($translatedFields['dosage'] ?? $prescription->getDosage() ?? '-');
        $duree = (string) ($translatedFields['dureeTraitement'] ?? $prescription->getDureeTraitement() ?? '-');
        $medicaments = (string) ($translatedFields['medicaments'] ?? $prescription->getMedicaments() ?? '-');
        $consignes = (string) ($translatedFields['consignes'] ?? $prescription->getConsignes() ?? '-');

        if ($locale === 'ar_tn') {
            $parts = [
                'الوصفة رقم ' . $prescription->getIdPrescription(),
                'المرات: ' . $this->toTunisianDarija($frequence),
                'الجرعة: ' . $this->toTunisianDarija($dosage),
                'المدة: ' . $this->toTunisianDarija($duree),
                'الفترة: ' . $periodStart . ' حتى ' . $periodEnd,
                'الدوايات: ' . $this->toTunisianDarija($medicaments),
                'التعليمات: ' . $this->toTunisianDarija($consignes),
            ];

            return implode('. ', $parts) . '.';
        }

        $parts = [
            $translator->trans('patient.prescription.speech.header', ['%id%' => $prescription->getIdPrescription()], 'messages', $locale),
            $translator->trans('patient.prescription.field.frequency', [], 'messages', $locale) . ': ' . $frequence,
            $translator->trans('patient.prescription.field.dosage', [], 'messages', $locale) . ': ' . $dosage,
            $translator->trans('patient.prescription.field.duration', [], 'messages', $locale) . ': ' . $duree,
            $translator->trans('patient.prescription.field.period', [], 'messages', $locale) . ': ' . $periodStart . ' '
                . $translator->trans('patient.prescription.period_to', [], 'messages', $locale) . ' ' . $periodEnd,
            $translator->trans('patient.prescription.field.medications', [], 'messages', $locale) . ': ' . $medicaments,
            $translator->trans('patient.prescription.field.instructions', [], 'messages', $locale) . ': ' . $consignes,
        ];

        return implode('. ', $parts) . '.';
    }

    /**
     * Translate prescription dynamic fields to requested UI language.
     *
     * @return array{frequence:string,dosage:string,dureeTraitement:string,medicaments:string,consignes:string}
     */
    private function translatePrescriptionFields(
        Prescription $prescription,
        string $targetLocale,
        HttpClientInterface $httpClient
    ): array {
        $source = [
            'frequence' => (string) ($prescription->getFrequence() ?? '-'),
            'dosage' => (string) ($prescription->getDosage() ?? '-'),
            'dureeTraitement' => (string) ($prescription->getDureeTraitement() ?? '-'),
            'medicaments' => (string) ($prescription->getMedicaments() ?? '-'),
            'consignes' => (string) ($prescription->getConsignes() ?? '-'),
        ];

        $target = in_array($targetLocale, ['fr', 'ar', 'en'], true) ? $targetLocale : 'en';
        $translated = [];

        foreach ($source as $field => $value) {
            if ($value === '-' || trim($value) === '') {
                $translated[$field] = '-';
                continue;
            }

            $normalized = $this->normalizeTextForTranslation($value);
            $translatedValue = $this->translateTextWithGoogle($normalized, $target, $httpClient);
            $translated[$field] = $this->postProcessTranslatedText($translatedValue, $target);
        }

        return $translated;
    }

    private function translateTextWithGoogle(
        string $text,
        string $targetLocale,
        HttpClientInterface $httpClient
    ): string {
        $target = match ($targetLocale) {
            'ar' => 'ar',
            'fr' => 'fr',
            default => 'en',
        };

        try {
            $response = $httpClient->request('GET', 'https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => $target,
                    'dt' => 't',
                    'q' => $text,
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                ],
                'timeout' => 12,
            ]);

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                return $text;
            }

            $translatedText = '';
            foreach ($data[0] as $chunk) {
                if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
                    $translatedText .= $chunk[0];
                }
            }

            $translatedText = trim($translatedText);
            return $translatedText !== '' ? $translatedText : $text;
        } catch (\Throwable) {
            return $text;
        }
    }

    private function normalizeTextForTranslation(string $text): string
    {
        // Improve machine translation quality for tokens like "2jours".
        return preg_replace('/(\d)([A-Za-z\x{00C0}-\x{024F}]+)/u', '$1 $2', $text) ?? $text;
    }

    private function postProcessTranslatedText(string $text, string $targetLocale): string
    {
        if ($targetLocale !== 'ar') {
            return $text;
        }

        $replacements = [
            'jours' => 'أيام',
            'jour' => 'يوم',
            'heures' => 'ساعات',
            'heure' => 'ساعة',
            'hours' => 'ساعات',
            'hour' => 'ساعة',
            'days' => 'أيام',
            'day' => 'يوم',
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $text);
    }

    private function toTunisianDarija(string $text): string
    {
        // User-provided simple dictionary: Arabic -> Tunisian Darja (Latin transcription).
        $darjaDict = [
            'تفاصيل الوصفة' => 'Tfasyl el-wasfa',
            'التكرار' => 'Tkrar',
            'الجرعة' => 'Jor3a',
            'المدة' => 'Moda dawa',
            'الفترة' => 'Fatra mtaa dawa',
            'الأدوية' => 'Dawa',
            'التعليمات' => 'Instructions',
            'ساعات' => 'saa3at',
            'أيام' => 'ayyam',
            'ساعة' => 'saa3a',
            'يوم' => 'nhar',
            'إلى' => 'ila',
        ];

        return strtr($text, $darjaDict);
    }

}
