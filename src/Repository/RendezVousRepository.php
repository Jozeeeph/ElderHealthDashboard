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
    public function findForPersonnelPaginated(Utilisateur $personnel, int $page, int $perPage, ?array $etats = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->addSelect("(CASE WHEN r.etat = 'EN_ATTENTE' THEN 0 ELSE 1 END) AS HIDDEN actionPriority")
            ->andWhere('r.personnelMedical = :personnel')
            ->setParameter('personnel', $personnel)
            ->orderBy('actionPriority', 'ASC')
            ->addOrderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC');
        if ($etats !== null && $etats !== []) {
            $qb->andWhere('r.etat IN (:etats)')
                ->setParameter('etats', $etats);
        }

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
    public function findForPatientPaginated(Utilisateur $patient, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.personnelMedical', 'm')->addSelect('m')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patient)
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
     * @return RendezVous[]
     */
    public function findPendingForPersonnel(Utilisateur $personnel, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat = :etat')
            ->setParameter('personnel', $personnel)
            ->setParameter('etat', 'EN_ATTENTE')
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPendingForPersonnel(Utilisateur $personnel): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat = :etat')
            ->setParameter('personnel', $personnel)
            ->setParameter('etat', 'EN_ATTENTE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return RendezVous[]
     */
    public function findCancelledForPersonnel(Utilisateur $personnel, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat = :etat')
            ->setParameter('personnel', $personnel)
            ->setParameter('etat', 'ANNULEE')
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countCancelledForPersonnel(Utilisateur $personnel): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.etat = :etat')
            ->setParameter('personnel', $personnel)
            ->setParameter('etat', 'ANNULEE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasPlannedConflictForPersonnel(
        Utilisateur $personnel,
        \DateTimeInterface $date,
        \DateTimeInterface $heure,
        ?int $excludeId = null
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.date = :date')
            ->andWhere('r.heure = :heure')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('personnel', $personnel)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('heure', $heure->format('H:i:s'))
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE', 'EN_COURS']);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function hasPlannedOverlapForPersonnel(
        Utilisateur $personnel,
        \DateTimeInterface $date,
        \DateTimeInterface $heure,
        int $durationMinutes,
        ?int $excludeId = null
    ): bool {
        $candidateStart = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $heure->format('H:i:s')
        );
        if (!$candidateStart instanceof \DateTimeImmutable) {
            return false;
        }
        $candidateDuration = max(1, $durationMinutes);
        $candidateEnd = $candidateStart->modify('+' . $candidateDuration . ' minutes');

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.date = :date')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('personnel', $personnel)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE', 'EN_COURS']);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        /** @var RendezVous[] $items */
        $items = $qb->getQuery()->getResult();
        foreach ($items as $item) {
            $itemDate = $item->getDate();
            $itemHeure = $item->getHeure();
            if ($itemDate === null || $itemHeure === null) {
                continue;
            }

            $itemStart = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $itemDate->format('Y-m-d') . ' ' . $itemHeure->format('H:i:s')
            );
            if (!$itemStart instanceof \DateTimeImmutable) {
                continue;
            }

            $existingDuration = self::durationToMinutes($item->getTypeRendezVous()?->getDuree(), 45);
            $itemEnd = $itemStart->modify('+' . $existingDuration . ' minutes');

            if ($candidateStart < $itemEnd && $candidateEnd > $itemStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return RendezVous[]
     */
    public function findForPersonnelBetween(Utilisateur $personnel, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.personnelMedical = :personnel')
            ->andWhere('r.date BETWEEN :startDate AND :endDate')
            ->setParameter('personnel', $personnel)
            ->setParameter('startDate', $start->format('Y-m-d'))
            ->setParameter('endDate', $end->format('Y-m-d'))
            ->orderBy('r.date', 'ASC')
            ->addOrderBy('r.heure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RendezVous[]
     */
    public function findStatusNotificationsForPatient(Utilisateur $patient, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.personnelMedical', 'm')->addSelect('m')
            ->leftJoin('r.typeRendezVous', 't')->addSelect('t')
            ->andWhere('r.patient = :patient')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('patient', $patient)
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE', 'REFUSEE'])
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.heure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countStatusNotificationsForPatient(Utilisateur $patient): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.patient = :patient')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('patient', $patient)
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE', 'REFUSEE'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int[]
     */
    public function findStatusNotificationIdsForPatient(Utilisateur $patient): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.id AS id')
            ->andWhere('r.patient = :patient')
            ->andWhere('r.etat IN (:etats)')
            ->setParameter('patient', $patient)
            ->setParameter('etats', ['PLANIFIE', 'PLANIFIEE', 'REFUSEE'])
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    public static function durationToMinutes(?string $rawDuration, int $default = 45): int
    {
        if ($rawDuration === null) {
            return max(1, $default);
        }

        if (preg_match('/\d+/', $rawDuration, $matches) === 1) {
            return max(1, (int) $matches[0]);
        }

        return max(1, $default);
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
