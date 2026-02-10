<?php

namespace App\Repository;

use App\Entity\RendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    public function countForPersonnel(\App\Entity\Utilisateur $personnel): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.personnelMedical = :personnel')
            ->setParameter('personnel', $personnel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPlannedForPersonnel(\App\Entity\Utilisateur $personnel): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('personnel', $personnel)
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return \App\Entity\RendezVous[]
     */
    public function findPlannedForPersonnel(\App\Entity\Utilisateur $personnel, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('personnel', $personnel)
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE'])
            ->orderBy('r.date', 'ASC')
            ->addOrderBy('r.heure', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return RendezVous[] Returns an array of RendezVous objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?RendezVous
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
