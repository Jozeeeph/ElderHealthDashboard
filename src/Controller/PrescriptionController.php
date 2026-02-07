<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\Prescription;
use App\Form\PrescriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/prescriptions', name: 'prescription_')]
class PrescriptionController extends AbstractController
{
    #[Route('/consultation/{id}/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        if ($consultation->getPrescription()) {
            $this->addFlash('info', 'Une prescription existe deja pour cette consultation.');
            return $this->redirectToRoute('prescription_show', [
                'id' => $consultation->getPrescription()->getIdPrescription(),
            ]);
        }

        $prescription = new Prescription();
        $prescription->setConsultation($consultation);

        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($prescription);
            $em->flush();
            $this->addFlash('success', 'Prescription ajoutee.');
            return $this->redirectToRoute('prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('BackOffice/prescription/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Ajouter',
                'form_action' => $this->generateUrl('prescription_new', ['id' => $consultation->getId()]),
            ]);
        }

        return $this->render('BackOffice/prescription/new.html.twig', [
            'form' => $form->createView(),
            'consultation' => $consultation,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Prescription $prescription): Response
    {
        return $this->render('BackOffice/prescription/show.html.twig', [
            'prescription' => $prescription,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Prescription $prescription, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Prescription mise a jour.');
            return $this->redirectToRoute('prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('BackOffice/prescription/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Mettre a jour',
                'form_action' => $this->generateUrl('prescription_edit', ['id' => $prescription->getIdPrescription()]),
            ]);
        }

        return $this->render('BackOffice/prescription/edit.html.twig', [
            'form' => $form->createView(),
            'prescription' => $prescription,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Prescription $prescription, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_prescription_' . $prescription->getIdPrescription(), $request->request->get('_token'))) {
            $em->remove($prescription);
            $em->flush();
            $this->addFlash('success', 'Prescription supprimee.');
        }

        return $this->redirectToRoute('consultation_index');
    }

    #[Route('/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(Prescription $prescription): Response
    {
        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('prescription_show', ['id' => $prescription->getIdPrescription()]);
        }

        $html = $this->renderView('BackOffice/prescription/pdf.html.twig', [
            'prescription' => $prescription,
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
