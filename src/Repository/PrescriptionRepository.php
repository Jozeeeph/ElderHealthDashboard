<?php

namespace App\Repository;

use App\Entity\Prescription;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prescription>
 */
class PrescriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prescription::class);
    }

    /**
     * @return list<Prescription>
     */
    public function findActiveForPatient(Utilisateur $patient, \DateTimeInterface $day): array
    {
        $dayStart = (new \DateTimeImmutable($day->format('Y-m-d')))->setTime(0, 0, 0);
        $dayEnd = $dayStart->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->innerJoin('p.consultation', 'c')
            ->andWhere('c.patient = :patient')
            ->andWhere('p.date_debut <= :dayEnd')
            ->andWhere('p.date_fin >= :dayStart')
            ->setParameter('patient', $patient)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('p.id_prescription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Prescription>
     */
    public function findEndingTodayForPatient(Utilisateur $patient, \DateTimeInterface $day): array
    {
        $dayStart = (new \DateTimeImmutable($day->format('Y-m-d')))->setTime(0, 0, 0);
        $dayEnd = $dayStart->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->innerJoin('p.consultation', 'c')
            ->andWhere('c.patient = :patient')
            ->andWhere('p.date_fin >= :dayStart')
            ->andWhere('p.date_fin <= :dayEnd')
            ->setParameter('patient', $patient)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('p.id_prescription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Prescription>
     */
    public function findEndingTodayForPersonnel(Utilisateur $personnel, \DateTimeInterface $day): array
    {
        $dayStart = (new \DateTimeImmutable($day->format('Y-m-d')))->setTime(0, 0, 0);
        $dayEnd = $dayStart->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->innerJoin('p.consultation', 'c')
            ->andWhere('c.personnelMedical = :personnel')
            ->andWhere('p.date_fin >= :dayStart')
            ->andWhere('p.date_fin <= :dayEnd')
            ->setParameter('personnel', $personnel)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('p.id_prescription', 'DESC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Prescription[] Returns an array of Prescription objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Prescription
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
