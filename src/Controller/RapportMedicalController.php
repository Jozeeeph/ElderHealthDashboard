<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\RapportMedical;
use App\Form\RapportMedicalType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rapports', name: 'rapport_medical_')]
class RapportMedicalController extends AbstractController
{
    #[Route('/consultation/{id}/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        if ($consultation->getRapportMedical()) {
            $this->addFlash('info', 'Un rapport medical existe deja pour cette consultation.');
            return $this->redirectToRoute('rapport_medical_show', [
                'id' => $consultation->getRapportMedical()->getIdRapport(),
            ]);
        }

        $rapport = new RapportMedical();
        $consultation->setRapportMedical($rapport);
        $rapport->setDateRapport(new \DateTime());

        $form = $this->createForm(RapportMedicalType::class, $rapport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rapport);
            $em->flush();
            $this->addFlash('success', 'Rapport medical ajoute.');
            return $this->redirectToRoute('rapport_medical_show', ['id' => $rapport->getIdRapport()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('BackOffice/rapport_medical/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Ajouter',
                'form_action' => $this->generateUrl('rapport_medical_new', ['id' => $consultation->getId()]),
            ]);
        }

        return $this->render('BackOffice/rapport_medical/new.html.twig', [
            'form' => $form->createView(),
            'consultation' => $consultation,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(RapportMedical $rapport): Response
    {
        return $this->render('BackOffice/rapport_medical/show.html.twig', [
            'rapport' => $rapport,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(RapportMedical $rapport, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RapportMedicalType::class, $rapport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Rapport medical mis a jour.');
            return $this->redirectToRoute('rapport_medical_show', ['id' => $rapport->getIdRapport()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('BackOffice/rapport_medical/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Mettre a jour',
                'form_action' => $this->generateUrl('rapport_medical_edit', ['id' => $rapport->getIdRapport()]),
            ]);
        }

        return $this->render('BackOffice/rapport_medical/edit.html.twig', [
            'form' => $form->createView(),
            'rapport' => $rapport,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(RapportMedical $rapport, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_rapport_' . $rapport->getIdRapport(), $request->request->get('_token'))) {
            $consultation = $rapport->getConsultation();
            if ($consultation) {
                $consultation->setRapportMedical(null);
            }
            $em->remove($rapport);
            $em->flush();
            $this->addFlash('success', 'Rapport medical supprime.');
        }

        return $this->redirectToRoute('consultation_index');
    }

    #[Route('/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(RapportMedical $rapport): Response
    {
        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('rapport_medical_show', ['id' => $rapport->getIdRapport()]);
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
}
