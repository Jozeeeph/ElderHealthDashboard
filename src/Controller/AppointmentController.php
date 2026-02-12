<?php

namespace App\Controller;

use App\Entity\TypeRendezVous;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Service\RendezVousEtatService;
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
    public function index(EntityManagerInterface $em, RendezVousEtatService $etatService): Response
    {
       
        $etatService->updateEtats();

       
        $rendezVousList = $em->getRepository(RendezVous::class)->findAll();
        $typesRendezVous = $em->getRepository(TypeRendezVous::class)->findAll();

        return $this->render('BackOffice/appointment/index.html.twig', [
            'rendezVousList' => $rendezVousList,
            'typesRendezVous' => $typesRendezVous,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $rdv = new RendezVous();
        $form = $this->createForm(GestionRendezVous::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $this->getUser();
            if ($admin instanceof Utilisateur) {
                $rdv->setAdmin($admin);
            }
            $em->persist($rdv);
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        
        return $this->renderFormWithModal($form);
    }

    #[Route('/edit/{id}', name: 'edit')]
    public function edit(RendezVous $rdv, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GestionRendezVous::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        
        return $this->renderFormWithModal($form);
    }

    #[Route('/type/new', name: 'type_new')]
    public function typeNew(Request $request, EntityManagerInterface $em): Response
    {
        $type = new TypeRendezVous();
        $form = $this->createForm(TypeRendezVousType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $this->getUser();
            if ($admin instanceof Utilisateur) {
                $type->setAdmin($admin);
            }
            $em->persist($type);
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        
        return $this->renderFormWithModal($form, 'type');
    }

    #[Route('/type/edit/{id}', name: 'type_edit')]
    public function typeEdit(TypeRendezVous $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TypeRendezVousType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('appointment_index');
        }

        
        return $this->renderFormWithModal($form, 'type');
    }

    #[Route('/delete/{id}', name: 'delete')]
    public function delete(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $em->remove($rdv);
        $em->flush();

        $this->addFlash('success', 'Le rendez-vous a Ã©tÃ© supprimÃ© avec succÃ¨s.');

        return $this->redirectToRoute('appointment_index');
    }

    #[Route('/type/delete/{id}', name: 'type_delete')]
    public function typeDelete(TypeRendezVous $type, EntityManagerInterface $em): Response
    {
        $em->remove($type);
        $em->flush();

        $this->addFlash('success', 'Le type de rendez-vous a Ã©tÃ© supprimÃ© avec succÃ¨s.');

        return $this->redirectToRoute('appointment_index');
    }

    
    private function renderFormWithModal($form, string $type = 'rdv'): Response
    {
        $template = 'BackOffice/appointment/form_modal.html.twig';
        
      
        $response = $this->render($template, [
            'form' => $form->createView(),
            'form_type' => $type
        ]);
        
        
        $response->headers->set('X-Form-Render', 'true');
        return $response;
    }
}
