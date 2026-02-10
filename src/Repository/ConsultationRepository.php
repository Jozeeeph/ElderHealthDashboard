<?php

namespace App\Repository;

use App\Entity\Consultation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC');

        if ($patient) {
            $patientLike = '%' . mb_strtolower($patient) . '%';
            $qb->andWhere('LOWER(p.nom) LIKE :patient OR LOWER(p.prenom) LIKE :patient')
                ->setParameter('patient', $patientLike);
        }

        if ($personnel) {
            $personnelLike = '%' . mb_strtolower($personnel) . '%';
            $qb->andWhere('LOWER(m.nom) LIKE :personnel OR LOWER(m.prenom) LIKE :personnel')
                ->setParameter('personnel', $personnelLike);
        }

        if ($date) {
            $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
            $end = $start->modify('+1 day');
            $qb->andWhere('c.dateConsultation >= :startDate')
                ->andWhere('c.dateConsultation < :endDate')
                ->setParameter('startDate', $start)
                ->setParameter('endDate', $end);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Consultation[]
     */
    public function findArchived(?string $patient, ?string $personnel, ?\DateTimeInterface $date, \DateTimeInterface $limitDate): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.personnelMedical', 'm')->addSelect('m')
            ->andWhere('c.etatConsultation IN (:etats)')
            ->setParameter('etats', ['terminee', 'terminée'])
            ->andWhere('c.dateConsultation <= :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->orderBy('c.dateConsultation', 'DESC')
            ->addOrderBy('c.heureConsultation', 'DESC');

        if ($patient) {
            $patientLike = '%' . mb_strtolower($patient) . '%';
            $qb->andWhere('LOWER(p.nom) LIKE :patient OR LOWER(p.prenom) LIKE :patient')
                ->setParameter('patient', $patientLike);
        }

        if ($personnel) {
            $personnelLike = '%' . mb_strtolower($personnel) . '%';
            $qb->andWhere('LOWER(m.nom) LIKE :personnel OR LOWER(m.prenom) LIKE :personnel')
                ->setParameter('personnel', $personnelLike);
        }

        if ($date) {
            $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
            $end = $start->modify('+1 day');
            $qb->andWhere('c.dateConsultation >= :startDate')
                ->andWhere('c.dateConsultation < :endDate')
                ->setParameter('startDate', $start)
                ->setParameter('endDate', $end);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Consultation[]
     */
    public function findByPatient(\App\Entity\Utilisateur $patient): array
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



//    /**
//     * @return Consultation[] Returns an array of Consultation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Consultation
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
