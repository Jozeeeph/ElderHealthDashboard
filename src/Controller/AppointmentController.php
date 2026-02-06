<?php

namespace App\Controller;
use App\Entity\TypeRendezVous;
use App\Entity\RendezVous;

use App\Form\TypeRendezVousType;
use App\Form\GestionRendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/appointments', name: 'appointment_')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('appointment/index.html.twig', [
            'rendezVousList' => $em->getRepository(RendezVous::class)->findAll(),
            'typesRendezVous' => $em->getRepository(TypeRendezVous::class)->findAll() // Ajoutez cette ligne
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $rdv = new RendezVous();
        $form = $this->createForm(GestionRendezVous::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rdv);
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        return $this->render('appointment/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un rendez-vous'
        ]);
    }

    #[Route('/rdv/{id}/edit', name: 'edit')]
    public function edit(RendezVous $rdv, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GestionRendezVous::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        return $this->render('appointment/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le rendez-vous'
        ]);
    }

    #[Route('/rdv/{id}', name: 'show')]
    public function show(RendezVous $rdv): Response
    {
        return $this->render('appointment/show.html.twig', [
            'rendezVous' => $rdv
        ]);
    }

    #[Route('/rdv/{id}/delete', name: 'delete')]
    public function delete(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $em->remove($rdv);
        $em->flush();

        return $this->redirectToRoute('appointment_index');
    }

    /* ===================== TYPE RENDEZ-VOUS ===================== */

   #[Route('/types', name: 'type_index')]
public function typeIndex(EntityManagerInterface $em): Response
{
    return $this->render('appointment/index.html.twig', [
        'rendezVousList' => $em->getRepository(RendezVous::class)->findAll(),
        'typesRendezVous' => $em->getRepository(TypeRendezVous::class)->findAll(),
    ]);
}


    #[Route('/types/new', name: 'type_new')]
    public function typeNew(Request $request, EntityManagerInterface $em): Response
    {
        $type = new TypeRendezVous();
        $form = $this->createForm(TypeRendezVousType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($type);
            $em->flush();
            return $this->redirectToRoute('appointment_type_index');
        }

        return $this->render('appointment/form1.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un type de rendez-vous'
        ]);
    }

    #[Route('/types/{id}/edit', name: 'type_edit')]
    public function typeEdit(TypeRendezVous $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TypeRendezVousType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('appointment_type_index');
        }

        return $this->render('appointment/form1.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le type'
        ]);
    }

    #[Route('/types/{id}', name: 'type_show')]
    public function typeShow(TypeRendezVous $type): Response
    {
        return $this->render('appointment/show1.html.twig', [
            'type' => $type
        ]);
    }

    #[Route('/types/{id}/delete', name: 'type_delete')]
    public function typeDelete(TypeRendezVous $type, EntityManagerInterface $em): Response
    {
        $em->remove($type);
        $em->flush();
        return $this->redirectToRoute('appointment_type_index');
    }
}