<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consultation>
 */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /**
     * @return Consultation[]
     */
    public function findFiltered(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date
    ): array {
        return $this->buildFilteredQueryBuilder($patient, $personnel, $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: array<int, Consultation>, total: int, page: int, perPage: int, pages: int}
     */
    public function findFilteredPaginated(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date,
        int $page,
        int $perPage
    ): array {
        return $this->paginateQueryBuilder(
            $this->buildFilteredQueryBuilder($patient, $personnel, $date),
            $page,
            $perPage
        );
    }

    /**
     * @return Consultation[]
     */
    public function findArchived(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date,
        \DateTimeInterface $limitDate
    ): array {
        return $this->buildArchivedQueryBuilder($patient, $personnel, $date, $limitDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: array<int, Consultation>, total: int, page: int, perPage: int, pages: int}
     */
    public function findArchivedPaginated(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date,
        \DateTimeInterface $limitDate,
        int $page,
        int $perPage
    ): array {
        return $this->paginateQueryBuilder(
            $this->buildArchivedQueryBuilder($patient, $personnel, $date, $limitDate),
            $page,
            $perPage
        );
    }

    /**
     * @return Consultation[]
     */
    public function findByPatient(Utilisateur $patient): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->andWhere('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Consultation[]
     */
    public function findForPersonnel(
        Utilisateur $personnel,
        ?string $patient,
        ?\DateTimeInterface $date
    ): array {
        return $this->buildForPersonnelQueryBuilder($personnel, $patient, $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: array<int, Consultation>, total: int, page: int, perPage: int, pages: int}
     */
    public function findForPersonnelPaginated(
        Utilisateur $personnel,
        ?string $patient,
        ?\DateTimeInterface $date,
        int $page,
        int $perPage
    ): array {
        return $this->paginateQueryBuilder(
            $this->buildForPersonnelQueryBuilder($personnel, $patient, $date),
            $page,
            $perPage
        );
    }

    private function buildFilteredQueryBuilder(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC');

        $this->applyPersonNameFilter($qb, 'p', 'patient', $patient);
        $this->applyPersonNameFilter($qb, 'm', 'personnel', $personnel);
        $this->applyDateFilter($qb, $date);

        return $qb;
    }

    private function buildArchivedQueryBuilder(
        ?string $patient,
        ?string $personnel,
        ?\DateTimeInterface $date,
        \DateTimeInterface $limitDate
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->andWhere('c.etatConsultation IN (:etats)')
            ->setParameter('etats', ['terminee', 'terminée', 'terminÃ©e'])
            ->andWhere('c.dateConsultation <= :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC');

        $this->applyPersonNameFilter($qb, 'p', 'patient', $patient);
        $this->applyPersonNameFilter($qb, 'm', 'personnel', $personnel);
        $this->applyDateFilter($qb, $date);

        return $qb;
    }

    private function buildForPersonnelQueryBuilder(
        Utilisateur $personnel,
        ?string $patient,
        ?\DateTimeInterface $date
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->andWhere('c.personnelMedical = :personnel')
            ->setParameter('personnel', $personnel)
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC');

        $this->applyPersonNameFilter($qb, 'p', 'patient', $patient);
        $this->applyDateFilter($qb, $date);

        return $qb;
    }

    private function applyDateFilter(QueryBuilder $qb, ?\DateTimeInterface $date): void
    {
        if (!$date) {
            return;
        }

        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = $start->modify('+1 day');
        $qb->andWhere('c.dateConsultation >= :startDate')
            ->andWhere('c.dateConsultation < :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end);
    }

    private function applyPersonNameFilter(
        QueryBuilder $qb,
        string $alias,
        string $paramName,
        ?string $value
    ): void {
        $value = $value !== null ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : null;
        if ($value === null || $value === '') {
            return;
        }

        $like = '%' . mb_strtolower($value) . '%';
        $qb->andWhere(sprintf(
            '(LOWER(%1$s.nom) LIKE :%2$s OR LOWER(%1$s.prenom) LIKE :%2$s OR LOWER(CONCAT(%1$s.prenom, \' \', %1$s.nom)) LIKE :%2$s OR LOWER(CONCAT(%1$s.nom, \' \', %1$s.prenom)) LIKE :%2$s)',
            $alias,
            $paramName
        ))->setParameter($paramName, $like);
    }

    /**
     * @return array{items: array<int, Consultation>, total: int, page: int, perPage: int, pages: int}
     */
    private function paginateQueryBuilder(QueryBuilder $qb, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT c.id)');

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
}

