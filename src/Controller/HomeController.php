<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // ✅ Page d'accueil publique (ou redirection)
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si admin connecté ➜ rediriger vers sa page d'accueil admin
        if ($user instanceof Utilisateur && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_home');
        }

        // Sinon, page d'accueil (publique / front)
        return $this->render('front/home.html.twig');
    }

    // ✅ Page d'accueil ADMIN (dynamique - no DB changes)
    #[Route('/admin/home', name: 'admin_home')]
    public function adminHome(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Utilisateur::class);

        $totalUsers = (int) $repo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pendingCount = (int) $repo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.accountStatus = :st')
            ->setParameter('st', Utilisateur::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $lastPending = $repo->createQueryBuilder('u')
            ->andWhere('u.accountStatus = :st')
            ->setParameter('st', Utilisateur::STATUS_PENDING)
            ->orderBy('u.id', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        return $this->render('BackOffice/home/index.html.twig', [
            'totalUsers'   => $totalUsers,
            'pendingCount' => $pendingCount,
            'lastPending'  => $lastPending,
            'now'          => new \DateTimeImmutable(),
        ]);
    }
}
