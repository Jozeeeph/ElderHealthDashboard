<?php

namespace App\Controller\FrontControllers;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Repository\RendezVousRepository;
use App\Service\GoogleCalendarService;
use App\Service\GoogleCalendarSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/infermier/rendezvous', name: 'front_infermier_rendezvous_')]
class RendezVousPersonnelController extends AbstractController
{
    private const PER_PAGE = 6;

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

    #[Route('/', name: 'index')]
    public function index(Request $request, RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->requirePersonnel();
        $page = max(1, $request->query->getInt('page', 1));

        return $this->renderList($rendezVousRepository, $user, $page, null, 'all');
    }

    #[Route('/planifies', name: 'planned')]
    public function planned(Request $request, RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->requirePersonnel();
        $page = max(1, $request->query->getInt('page', 1));

        return $this->renderList($rendezVousRepository, $user, $page, ['PLANIFIE', 'PLANIFIEE'], 'planned');
    }

    #[Route('/accept/{id}', name: 'accept')]
    public function accept(
        RendezVous $rdv,
        EntityManagerInterface $em,
        GoogleCalendarSyncService $googleCalendarSyncService,
        GoogleCalendarService $googleCalendarService
    ): Response
    {
        $user = $this->requirePersonnel();
        if ($rdv->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $rdv->setEtat('PLANIFIE');
        $em->flush();

        if ($googleCalendarSyncService->isEnabled()) {
            $synced = $googleCalendarSyncService->syncPlannedRendezVous($rdv);
            if (!$synced) {
                $details = $googleCalendarSyncService->getLastError();
                $this->addFlash(
                    'warning',
                    'Rendez-vous planifie, mais synchronisation Google Calendar echouee.'
                    . ($details ? ' Detail: ' . $details : '')
                );
            } else {
                $googleUrl = $googleCalendarService->getCalendarWebUrl();
                if (is_string($googleUrl) && $googleUrl !== '') {
                    return $this->redirect($googleUrl);
                }
            }
        }

        $this->addFlash('success', 'Rendez-vous accepte.');
        return $this->redirectToRoute('front_infermier_rendezvous_index');
    }

    #[Route('/refuse/{id}', name: 'refuse')]
    public function refuse(RendezVous $rdv, EntityManagerInterface $em): Response
    {
        $user = $this->requirePersonnel();
        if ($rdv->getPersonnelMedical()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $rdv->setEtat('REFUSEE');
        $em->flush();

        $this->addFlash('success', 'Rendez-vous refuse.');
        return $this->redirectToRoute('front_infermier_rendezvous_index');
    }

    private function renderList(
        RendezVousRepository $rendezVousRepository,
        Utilisateur $user,
        int $page,
        ?array $etats,
        string $currentView
    ): Response {
        $pagination = $rendezVousRepository->findForPersonnelPaginated($user, $page, self::PER_PAGE, $etats);
        $notifications = $rendezVousRepository->findPendingForPersonnel($user, 6);

        return $this->render('FrontOffice/infermier/rendezvous/index.html.twig', [
            'rendezVousList' => $pagination['items'],
            'pagination' => $pagination,
            'notifications' => $notifications,
            'nurseName' => $user->getPrenom(),
            'currentView' => $currentView,
            'paginationRoute' => $currentView === 'planned'
                ? 'front_infermier_rendezvous_planned'
                : 'front_infermier_rendezvous_index',
        ]);
    }
}
