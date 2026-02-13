<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use App\Entity\Utilisateur;
use App\Form\GestionRendezVous;
use App\Form\TypeRendezVousType;
use App\Repository\RendezVousRepository;
use App\Service\RendezVousEtatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/appointments', name: 'appointment_')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        RendezVousEtatService $etatService,
        RendezVousRepository $rendezVousRepository
    ): Response {
        $etatService->updateEtats();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 2;
        $pagination = $rendezVousRepository->findAllPaginated($page, $perPage);
        $rendezVousList = $pagination['items'];
        $typesRendezVous = $em->getRepository(TypeRendezVous::class)->findAll();

        return $this->render('BackOffice/appointment/index.html.twig', [
            'rendezVousList' => $rendezVousList,
            'rdvPagination' => $pagination,
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

            return $this->redirectToRoute('appointment_index', ['tab' => 'types']);
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

            return $this->redirectToRoute('appointment_index', ['tab' => 'types']);
        }

        return $this->renderFormWithModal($form, 'type');
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(RendezVous $rdv, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $em->remove($rdv);
            $em->flush();
            $this->addFlash('success', 'Le rendez-vous a ete supprime avec succes.');
        }

        return $this->redirectToRoute('appointment_index');
    }

    #[Route('/type/delete/{id}', name: 'type_delete', methods: ['POST'])]
    public function typeDelete(TypeRendezVous $type, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_type_rdv_' . $type->getId(), (string) $request->request->get('_token'))) {
            // Fallback safety: works even if DB FK is not yet ON DELETE CASCADE.
            $em->createQueryBuilder()
                ->delete(RendezVous::class, 'r')
                ->andWhere('r.typeRendezVous = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->execute();

            $em->remove($type);
            $em->flush();
            $this->addFlash('success', 'Le type de rendez-vous a ete supprime avec succes.');
        }

        return $this->redirectToRoute('appointment_index', ['tab' => 'types']);
    }

    private function renderFormWithModal($form, string $type = 'rdv'): Response
    {
        $response = $this->render('BackOffice/appointment/form_modal.html.twig', [
            'form' => $form->createView(),
            'form_type' => $type,
        ]);

        $response->headers->set('X-Form-Render', 'true');
        return $response;
    }
}
