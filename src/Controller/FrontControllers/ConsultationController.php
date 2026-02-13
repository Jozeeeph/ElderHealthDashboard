<?php

namespace App\Controller\FrontControllers;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/infermier/consultations', name: 'front_infermier_consultation_')]
class ConsultationController extends AbstractController
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

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, ConsultationRepository $repo): Response
    {
        $user = $this->requirePersonnel();
        $patient = trim((string) $request->query->get('patient', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 2;
        $dateInput = $request->query->get('date');
        $date = null;
        if (is_string($dateInput) && $dateInput !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateInput) ?: null;
            if (!$date) {
                $date = \DateTimeImmutable::createFromFormat('d/m/Y', $dateInput) ?: null;
            }
        }

        $pagination = $repo->findForPersonnelPaginated(
            $user,
            $patient !== '' ? $patient : null,
            $date,
            $page,
            $perPage
        );

        return $this->render('FrontOffice/infermier/consultations/index.html.twig', [
            'consultations' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => [
                'patient' => $patient,
                'date' => $dateInput,
            ],
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$consultation->getPersonnelMedical()) {
                $consultation->setPersonnelMedical($user);
            }
            $consultation->setCreatedBy($user);
            $consultation->setCreatedRole($user->getRoleMetier());
            $consultation->setCreatedAt(new \DateTime());
            if (!$consultation->getEtatConsultation()) {
                $consultation->setEtatConsultation('en_cours');
            }

            $em->persist($consultation);
            $em->flush();

            $this->addFlash('success', 'Consultation ajoutee !');
            return $this->redirectToRoute('front_infermier_consultation_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('FrontOffice/infermier/consultations/_form.html.twig', [
                'form' => $form->createView(),
                'submit_label' => 'Ajouter',
                'form_action' => $this->generateUrl('front_infermier_consultation_new'),
            ]);
        }

        return $this->render('FrontOffice/infermier/consultations/new.html.twig', [
            'form' => $form->createView(),
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        if ($consultation->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Consultation mise a jour !');
            return $this->redirectToRoute('front_infermier_consultation_index');
        }

        return $this->render('FrontOffice/infermier/consultations/edit.html.twig', [
            'consultation' => $consultation,
            'form' => $form->createView(),
            'nurseName' => $user->getPrenom(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Consultation $consultation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        if ($consultation->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if ($this->isCsrfTokenValid('delete_consultation_' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
            $this->addFlash('success', 'Consultation supprimee !');
        }

        return $this->redirectToRoute('front_infermier_consultation_index');
    }
}
