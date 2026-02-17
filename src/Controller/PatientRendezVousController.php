<?php

namespace App\Controller;

use Dompdf\Dompdf;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Form\PatientRendezVousType;
use App\Repository\RendezVousRepository;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient', name: 'patient_rendezvous_')]
class PatientRendezVousController extends AbstractController
{
    private const PER_PAGE = 6;

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/rendezvous', name: 'index')]
    public function index(Request $request, EntityManagerInterface $em, RendezVousRepository $rendezVousRepository): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        $rendezVousList = [];
        $patient = $em->getRepository(Utilisateur::class)->find($patient->getId());
        $pagination = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'perPage' => self::PER_PAGE,
            'pages' => 1,
        ];
        $notifications = [];
        $paidRdvIds = [];
        if ($patient) {
            $page = max(1, $request->query->getInt('page', 1));
            $pagination = $rendezVousRepository->findForPatientPaginated($patient, $page, self::PER_PAGE);
            $rendezVousList = $pagination['items'];
            $notifications = $rendezVousRepository->findStatusNotificationsForPatient($patient, 6);

            // Auto-clear patient notifications when opening rendez-vous page
            $session = $request->getSession();
            if ($session !== null) {
                $seen = $session->get('patient_rdv_seen_notification_ids', []);
                if (!is_array($seen)) {
                    $seen = [];
                }
                $currentNotifIds = $rendezVousRepository->findStatusNotificationIdsForPatient($patient);
                $merged = array_values(array_unique(array_map('intval', array_merge($seen, $currentNotifIds))));
                $session->set('patient_rdv_seen_notification_ids', $merged);

                $paid = $session->get('patient_paid_rdv_ids', []);
                if (is_array($paid)) {
                    $paidRdvIds = array_map('intval', $paid);
                }
            }
        }

        return $this->render('FrontOffice/patient/rendezvous/index.html.twig', [
            'rendezVousList' => $rendezVousList,
            'pagination' => $pagination,
            'notifications' => $notifications,
            'paidRdvIds' => $paidRdvIds,
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

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/delete/{id}', name: 'delete')]
    public function delete(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        $allowedStates = ['ANNULEE', 'TERMINE', 'TERMINEE', 'REFUSEE'];
        if (!in_array((string) $rdv->getEtat(), $allowedStates, true)) {
            $this->addFlash('success', 'Suppression non autorisee pour ce rendez-vous.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        $em->remove($rdv);
        $em->flush();

        $this->addFlash('success', 'Rendez-vous supprime.');
        return $this->redirectToRoute('patient_rendezvous_index');
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/rendezvous/{id}/pay-online', name: 'pay_online', methods: ['POST'])]
    public function payOnline(
        RendezVous $rdv,
        Request $request,
        StripeCheckoutService $stripeCheckoutService
    ): Response {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array((string) $rdv->getEtat(), ['PLANIFIE', 'PLANIFIEE'], true)) {
            $this->addFlash('warning', 'Paiement en ligne disponible uniquement pour les rendez-vous acceptes.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        if (!$this->isCsrfTokenValid('patient_pay_online_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $successUrl = $this->generateUrl('patient_rendezvous_payment_success', [
                'id' => $rdv->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            $cancelUrl = $this->generateUrl('patient_rendezvous_payment_cancel', [
                'id' => $rdv->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $checkoutUrl = $stripeCheckoutService->createCheckoutUrl($rdv, $patient, $successUrl, $cancelUrl);
            return $this->redirect($checkoutUrl);
        } catch (\Throwable $e) {
            $this->addFlash('warning', 'Paiement en ligne indisponible pour le moment: ' . $e->getMessage());
            return $this->redirectToRoute('patient_rendezvous_index');
        }
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/rendezvous/{id}/payment-success', name: 'payment_success', methods: ['GET'])]
    public function paymentSuccess(RendezVous $rdv, Request $request): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        if ($session !== null) {
            $paid = $session->get('patient_paid_rdv_ids', []);
            if (!is_array($paid)) {
                $paid = [];
            }
            $paid[] = (int) $rdv->getId();
            $session->set('patient_paid_rdv_ids', array_values(array_unique(array_map('intval', $paid))));
        }

        $this->addFlash('success', 'Paiement confirme pour le rendez-vous #' . $rdv->getId() . '.');
        return $this->redirectToRoute('patient_rendezvous_index');
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/rendezvous/{id}/payment-cancel', name: 'payment_cancel', methods: ['GET'])]
    public function paymentCancel(RendezVous $rdv): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->addFlash('warning', 'Paiement annule.');
        return $this->redirectToRoute('patient_rendezvous_index');
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/rendezvous/{id}/invoice', name: 'invoice', methods: ['GET'])]
    public function invoice(RendezVous $rdv): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur || $rdv->getPatient()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array((string) $rdv->getEtat(), ['PLANIFIE', 'PLANIFIEE'], true)) {
            $this->addFlash('warning', 'Facture disponible uniquement pour les rendez-vous acceptes.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        if (!class_exists(Dompdf::class)) {
            $this->addFlash('danger', 'Generation PDF indisponible. Installez dompdf/dompdf.');
            return $this->redirectToRoute('patient_rendezvous_index');
        }

        $html = $this->renderView('FrontOffice/patient/rendezvous/invoice.html.twig', [
            'rdv' => $rdv,
            'patient' => $patient,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture-rendez-vous-' . $rdv->getId() . '.pdf"',
        ]);
    }

    #[IsGranted('ROLE_PATIENT')]
    #[Route('/notifications/clear', name: 'notifications_clear', methods: ['POST'])]
    public function clearNotifications(Request $request, RendezVousRepository $rendezVousRepository): Response
    {
        $patient = $this->getUser();
        if (!$patient instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('patient_clear_notifications', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $session = $request->getSession();
        if ($session !== null) {
            $seen = $session->get('patient_rdv_seen_notification_ids', []);
            if (!is_array($seen)) {
                $seen = [];
            }

            $currentNotifIds = $rendezVousRepository->findStatusNotificationIdsForPatient($patient);
            $merged = array_values(array_unique(array_map('intval', array_merge($seen, $currentNotifIds))));
            $session->set('patient_rdv_seen_notification_ids', $merged);
        }

        $this->addFlash('success', 'Notifications supprimees.');

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('patient_rendezvous_index');
    }

}
