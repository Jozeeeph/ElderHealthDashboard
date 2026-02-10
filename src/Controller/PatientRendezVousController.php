<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Form\PatientRendezVousType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/rendezvous', name: 'patient_rendezvous_')]
class PatientRendezVousController extends AbstractController
{
    #[IsGranted('ROLE_PATIENT')]
    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        $rendezVousList = [];
        $patient = $em->getRepository(Utilisateur::class)->find($patient->getId());
        if ($patient) {
            $rendezVousList = $em->getRepository(RendezVous::class)->findBy(
                ['patient' => $patient],
                ['date' => 'DESC', 'heure' => 'DESC']
            );
        }

        return $this->render('FrontOffice/patient/rendezvous/index.html.twig', [
            'rendezVousList' => $rendezVousList,
        ]);
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $rdv = new RendezVous();
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        $patient = $em->getRepository(Utilisateur::class)->find($patient->getId());
        if ($patient) {
            $rdv->setPatient($patient);
        }
        $rdv->setEtat('EN_ATTENTE');
        $form = $this->createForm(PatientRendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rdv->setEtat('EN_ATTENTE');
            $em->persist($rdv);
            $em->flush();

            $this->addFlash('success', 'Demande envoyee. En attente de validation par le personnel medical.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        return $this->render('FrontOffice/patient/rendezvous/form.html.twig', [
            'form' => $form->createView(),
            'mode' => 'new',
        ]);
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/edit/{id}', name: 'edit')]
    public function edit(RendezVous $rdv, Request $request, EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }
        $patient = $em->getRepository(Utilisateur::class)->find($patient->getId());
        if ($patient) {
            $rdv->setPatient($patient);
        }
        $rdv->setEtat('EN_ATTENTE');

        $form = $this->createForm(PatientRendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rdv->setEtat('EN_ATTENTE');
            $em->flush();

            $this->addFlash('success', 'Rendez-vous modifie. En attente de validation.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        return $this->render('FrontOffice/patient/rendezvous/form.html.twig', [
            'form' => $form->createView(),
            'mode' => 'edit',
        ]);
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/accept/{id}', name: 'accept')]
    public function accept(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        $rdv->setEtat('PLANIFIE');
        $em->flush();

        $this->addFlash('success', 'Rendez-vous accepte.');
        return $this->redirectToRoute('patient_rendezvous_index');
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/cancel/{id}', name: 'cancel')]
    public function cancel(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        $rdv->setEtat('ANNULEE');
        $em->flush();

        $this->addFlash('success', 'Rendez-vous annule.');
        return $this->redirectToRoute('patient_rendezvous_index');
    }

}