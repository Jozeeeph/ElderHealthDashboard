<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @return array{items: array<int, RendezVous>, total: int, page: int, perPage: int, pages: int}
     */
    public function findAllPaginated(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.personnelMedical', 'm')->addSelect('m')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC');

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT r.id)');

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $qb->setFirstResult($offset)->setMaxResults($perPage);
        $paginator = new Paginator($qb, true);

        return [
            'items' => iterator_to_array($paginator->getIterator(), false),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{items: array<int, RendezVous>, total: int, page: int, perPage: int, pages: int}
     */
    public function findForPersonnelPaginated(Utilisateur $personnel, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->setParameter('personnel', $personnel)
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC');

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT r.id)');

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $qb->setFirstResult($offset)->setMaxResults($perPage);
        $paginator = new Paginator($qb, true);

        return [
            'items' => iterator_to_array($paginator->getIterator(), false),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
        ];
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
