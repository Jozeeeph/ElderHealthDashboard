<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consultations', name: 'consultation_')]
class ConsultationController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ConsultationRepository $repo): Response
    {
        return $this->render('consultation/index.html.twig', [
            'consultations' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof \App\Entity\Utilisateur) {
                $consultation->setCreatedBy($user);
                $consultation->setCreatedRole($user->getRole() ? $user->getRole()->value : null);
            }
            $consultation->setCreatedAt(new \DateTime());

            $em->persist($consultation);
            $em->flush();

            $this->addFlash('success', 'Consultation ajoutée !');
            return $this->redirectToRoute('consultation_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('consultation/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Ajouter',
                'form_action' => $this->generateUrl('consultation_new'),
            ]);
        }

        return $this->render('consultation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Consultation mise à jour !');
            return $this->redirectToRoute('consultation_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('consultation/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Mettre à jour',
                'form_action' => $this->generateUrl('consultation_edit', ['id' => $consultation->getId()]),
            ]);
        }

        return $this->render('consultation/edit.html.twig', [
            'consultation' => $consultation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_consultation_' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
            $this->addFlash('success', 'Consultation supprimée !');
        }

        return $this->redirectToRoute('consultation_index');
    }
}
