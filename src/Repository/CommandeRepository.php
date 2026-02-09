<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function save(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les commandes par utilisateur
     */
    public function findCommandesByUser($userId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.utilisateur', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les commandes par statut
     */
    public function findCommandesByStatut($statut): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les commandes par statut
     */
    public function countCommandesByStatut($statut): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule le revenu total des commandes livrées
     */
    public function getTotalRevenue(): string
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantTotal)')
            ->where('c.statut = :statut')
            ->setParameter('statut', 'livree')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0.00';
    }

    /**
     * Trouve les commandes récentes (7 derniers jours)
     */
    public function findRecentCommandes($days = 7): array
    {
        $date = new \DateTime();
        $date->modify('-' . $days . ' days');

        return $this->createQueryBuilder('c')
            ->where('c.dateCommande >= :date')
            ->setParameter('date', $date)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les commandes avec leurs équipements
     */
    public function findWithEquipements(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.equipements', 'e')
            ->addSelect('e')
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques mensuelles
     */
    public function getMonthlyStats($year = null, $month = null): array
    {
        if ($year === null) {
            $year = date('Y');
        }
        if ($month === null) {
            $month = date('m');
        }

        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id) as total_commandes')
            ->addSelect('SUM(c.montantTotal) as revenue_total')
            ->addSelect('AVG(c.montantTotal) as moyenne_commande')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere('c.statut = :statut')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statut', 'livree')
            ->getQuery()
            ->getSingleResult();
    }
}