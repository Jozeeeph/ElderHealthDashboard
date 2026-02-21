<?php

namespace App\Controller\FrontControllers;

use App\Entity\Consultation;
use App\Entity\RapportMedical;
use App\Entity\Utilisateur;
use App\Form\RapportMedicalType;
use App\Service\ClinicalSeverityService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/infermier/rapports', name: 'front_infermier_rapport_')]
class RapportMedicalController extends AbstractController
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
        ClinicalSeverityService $clinicalSeverityService
    ): Response
    {
        $user = $this->requirePersonnel();
        $this->assertConsultationOwned($consultation, $user);

        if ($consultation->getRapportMedical()) {
            $this->addFlash('info', 'Un rapport medical existe deja pour cette consultation.');
            return $this->redirectToRoute('front_infermier_rapport_show', [
                'id' => $consultation->getRapportMedical()->getIdRapport(),
            ]);
        }

        $rapport = new RapportMedical();
        $consultation->setRapportMedical($rapport);
        $rapport->setDateRapport(new \DateTime());
        $severity = $clinicalSeverityService->evaluate($consultation);
        if (!$rapport->getNiveauGravite()) {
            $rapport->setNiveauGravite($this->mapSeverityToRapportLevel($severity['level']));
        }

        $form = $this->createForm(RapportMedicalType::class, $rapport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rapport);
            $em->flush();
            $this->addFlash('success', 'Rapport medical ajoute.');
            return $this->redirectToRoute('front_infermier_rapport_show', ['id' => $rapport->getIdRapport()]);
        }

        return $this->render('FrontOffice/infermier/rapport_medical/new.html.twig', [
            'form' => $form->createView(),
            'consultation' => $consultation,
            'severity' => $severity,
            'severity_prefill_level' => $rapport->getNiveauGravite(),
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(RapportMedical $rapport): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $rapport->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        return $this->render('FrontOffice/infermier/rapport_medical/show.html.twig', [
            'rapport' => $rapport,
            'consultation' => $consultation,
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(RapportMedical $rapport, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $rapport->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        $form = $this->createForm(RapportMedicalType::class, $rapport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Rapport medical mis a jour.');
            return $this->redirectToRoute('front_infermier_rapport_show', ['id' => $rapport->getIdRapport()]);
        }

        return $this->render('FrontOffice/infermier/rapport_medical/edit.html.twig', [
            'form' => $form->createView(),
            'rapport' => $rapport,
            'consultation' => $consultation,
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(RapportMedical $rapport, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $rapport->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        if ($this->isCsrfTokenValid('delete_rapport_' . $rapport->getIdRapport(), $request->request->get('_token'))) {
            $consultation->setRapportMedical(null);
            $em->remove($rapport);
            $em->flush();
            $this->addFlash('success', 'Rapport medical supprime.');
        }

        return $this->redirectToRoute('front_infermier_consultation_index');
    }

    #[Route('/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(RapportMedical $rapport): Response
    {
        $user = $this->requirePersonnel();
        $consultation = $rapport->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }
        $this->assertConsultationOwned($consultation, $user);

        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('front_infermier_rapport_show', ['id' => $rapport->getIdRapport()]);
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
            $storedPath = ltrim((string) $rapport->getFichierPath(), '/\\');
            $publicPath = str_starts_with($storedPath, 'uploads' . DIRECTORY_SEPARATOR)
                || str_starts_with($storedPath, 'uploads/')
                ? $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $storedPath
                : $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rapports' . DIRECTORY_SEPARATOR . $storedPath;
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

    private function mapSeverityToRapportLevel(string $severityLevel): string
    {
        return match ($severityLevel) {
            'high' => 'eleve',
            'medium' => 'moyen',
            default => 'faible',
        };
    }
}
