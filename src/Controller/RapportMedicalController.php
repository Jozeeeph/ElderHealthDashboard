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

#[Route('/rapports', name: 'rapport_medical_')]
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
            return $this->render('rapport_medical/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Ajouter',
                'form_action' => $this->generateUrl('rapport_medical_new', ['id' => $consultation->getId()]),
            ]);
        }

        return $this->render('rapport_medical/new.html.twig', [
            'form' => $form->createView(),
            'consultation' => $consultation,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(RapportMedical $rapport): Response
    {
        return $this->render('rapport_medical/show.html.twig', [
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
            return $this->render('rapport_medical/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Mettre a jour',
                'form_action' => $this->generateUrl('rapport_medical_edit', ['id' => $rapport->getIdRapport()]),
            ]);
        }

        return $this->render('rapport_medical/edit.html.twig', [
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

        $html = $this->renderView('rapport_medical/pdf.html.twig', [
            'rapport' => $rapport,
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
