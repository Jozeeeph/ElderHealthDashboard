<?php

namespace App\Controller\FrontControllers;

use App\Entity\Consultation;
use App\Entity\Prescription;
use App\Entity\Utilisateur;
use App\Form\PrescriptionType;
use App\Service\MedicationSafetyService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/infermier/prescriptions', name: 'front_infermier_prescription_')]
class PrescriptionController extends AbstractController
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

    private function assertConsultationOwned(Consultation $consultation, Utilisateur $user): void
    {
        if ($consultation->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }

    #[Route('/consultation/{id}/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Consultation $consultation,
        Request $request,
        EntityManagerInterface $em,
        MedicationSafetyService $medicationSafetyService
    ): Response
    {
        $user = $this->requirePersonnel();
        $this->assertConsultationOwned($consultation, $user);

        if ($consultation->getPrescription()) {
            $this->addFlash('info', 'Une prescription existe deja pour cette consultation.');
            return $this->redirectToRoute('front_infermier_prescription_show', [
                'id' => $consultation->getPrescription()->getIdPrescription(),
            ]);
        }

        $prescription = new Prescription();
        $consultation->setPrescription($prescription);

        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);
        $interactionAnalysis = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $interactionAnalysis = $medicationSafetyService->analyze(
                $consultation,
                (string) $prescription->getMedicaments()
            );

            if ($interactionAnalysis['hasCritical']) {
                $form->get('medicaments')->addError(new FormError(
                    'Interaction medicamenteuse critique detectee. Corrigez la liste des medicaments avant validation.'
                ));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($prescription);
            $em->flush();

            if (is_array($interactionAnalysis) && count($interactionAnalysis['alerts']) > 0) {
                $criticalCount = count(array_filter(
                    $interactionAnalysis['alerts'],
                    static fn (array $alert): bool => $alert['severity'] === 'critical'
                ));
                $highCount = count(array_filter(
                    $interactionAnalysis['alerts'],
                    static fn (array $alert): bool => $alert['severity'] === 'high'
                ));

                if ($criticalCount > 0 || $highCount > 0) {
                    $this->addFlash(
                        'warning',
                        sprintf(
                            'Vigilance medicamenteuse: %d alerte(s) critique(s), %d alerte(s) elevee(s).',
                            $criticalCount,
                            $highCount
                        )
                    );
                }
            }

            $this->addFlash('success', 'Prescription ajoutee.');
            return $this->redirectToRoute('front_infermier_prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        return $this->render('FrontOffice/infermier/prescription/new.html.twig', [
            'form' => $form->createView(),
            'consultation' => $consultation,
            'interaction_check_url' => $this->generateUrl('front_infermier_prescription_interaction_check', ['id' => $consultation->getId()]),
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Prescription $prescription): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $prescription->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        return $this->render('FrontOffice/infermier/prescription/show.html.twig', [
            'prescription' => $prescription,
            'consultation' => $consultation,
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Prescription $prescription,
        Request $request,
        EntityManagerInterface $em,
        MedicationSafetyService $medicationSafetyService
    ): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $prescription->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);
        $interactionAnalysis = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $interactionAnalysis = $medicationSafetyService->analyze(
                $consultation,
                (string) $prescription->getMedicaments()
            );

            if ($interactionAnalysis['hasCritical']) {
                $form->get('medicaments')->addError(new FormError(
                    'Interaction medicamenteuse critique detectee. Corrigez la liste des medicaments avant validation.'
                ));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if (is_array($interactionAnalysis) && count($interactionAnalysis['alerts']) > 0) {
                $criticalCount = count(array_filter(
                    $interactionAnalysis['alerts'],
                    static fn (array $alert): bool => $alert['severity'] === 'critical'
                ));
                $highCount = count(array_filter(
                    $interactionAnalysis['alerts'],
                    static fn (array $alert): bool => $alert['severity'] === 'high'
                ));

                if ($criticalCount > 0 || $highCount > 0) {
                    $this->addFlash(
                        'warning',
                        sprintf(
                            'Vigilance medicamenteuse: %d alerte(s) critique(s), %d alerte(s) elevee(s).',
                            $criticalCount,
                            $highCount
                        )
                    );
                }
            }

            $this->addFlash('success', 'Prescription mise a jour.');
            return $this->redirectToRoute('front_infermier_prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        return $this->render('FrontOffice/infermier/prescription/edit.html.twig', [
            'form' => $form->createView(),
            'prescription' => $prescription,
            'consultation' => $consultation,
            'interaction_check_url' => $this->generateUrl('front_infermier_prescription_interaction_check', ['id' => $consultation->getId()]),
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/consultation/{id}/interaction-check', name: 'interaction_check', methods: ['POST'])]
    public function interactionCheck(
        Consultation $consultation,
        Request $request,
        MedicationSafetyService $medicationSafetyService
    ): JsonResponse {
        $user = $this->requirePersonnel();
        $this->assertConsultationOwned($consultation, $user);

        $payload = json_decode($request->getContent(), true);
        $medicaments = '';

        if (is_array($payload) && isset($payload['medicaments']) && is_string($payload['medicaments'])) {
            $medicaments = $payload['medicaments'];
        } else {
            $medicaments = (string) $request->request->get('medicaments', '');
        }

        $analysis = $medicationSafetyService->analyze($consultation, $medicaments);

        return $this->json([
            'ok' => true,
            'medications' => $analysis['medications'],
            'alerts' => $analysis['alerts'],
            'hasCritical' => $analysis['hasCritical'],
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Prescription $prescription, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $prescription->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        if ($this->isCsrfTokenValid('delete_prescription_' . $prescription->getIdPrescription(), $request->request->get('_token'))) {
            $consultation->setPrescription(null);
            $em->remove($prescription);
            $em->flush();
            $this->addFlash('success', 'Prescription supprimee.');
        }

        return $this->redirectToRoute('front_infermier_consultation_index');
    }

    #[Route('/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(Prescription $prescription): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $prescription->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('front_infermier_prescription_show', [
                'id' => $prescription->getIdPrescription(),
            ]);
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
}
