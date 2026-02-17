<?php

namespace App\Repository;

use App\Entity\Prescription;
use App\Entity\PrescriptionDoseAck;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrescriptionDoseAck>
 */
class PrescriptionDoseAckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrescriptionDoseAck::class);
    }

    /**
     * @param list<int> $prescriptionIds
     * @return array<string, true>
     */
    public function findDoneSlotKeys(array $prescriptionIds, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        if ($prescriptionIds === []) {
            return [];
        }

        try {
            $rows = $this->createQueryBuilder('a')
                ->select('IDENTITY(a.prescription) AS prescription_id', 'a.scheduledAt AS scheduled_at')
                ->andWhere('a.prescription IN (:ids)')
                ->andWhere('a.scheduledAt >= :start')
                ->andWhere('a.scheduledAt <= :end')
                ->setParameter('ids', $prescriptionIds)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getArrayResult();
        } catch (TableNotFoundException) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            $scheduled = $row['scheduled_at'] ?? null;
            $scheduledAt = $scheduled instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($scheduled)
                : new \DateTimeImmutable((string) $scheduled);

            $keys[(int) $row['prescription_id'] . '|' . $scheduledAt->format('Y-m-d H:i')] = true;
        }

        return $keys;
    }

    public function markDone(Prescription $prescription, \DateTimeImmutable $scheduledAt): PrescriptionDoseAck
    {
        $ack = $this->findOneBy([
            'prescription' => $prescription,
            'scheduledAt' => $scheduledAt,
        ]);

        if (!$ack) {
            $ack = (new PrescriptionDoseAck())
                ->setPrescription($prescription)
                ->setScheduledAt($scheduledAt);
            $this->getEntityManager()->persist($ack);
        }

        $ack->setDoneAt(new \DateTimeImmutable());

        return $ack;
    }

    /**
     * @return list<PrescriptionDoseAck>
     */
    public function findRecentDoneForPersonnel(
        Utilisateur $personnel,
        int $limit = 10,
        ?\DateTimeImmutable $since = null
    ): array {
        $since ??= (new \DateTimeImmutable())->modify('-24 hours');

        try {
            return $this->createQueryBuilder('a')
                ->innerJoin('a.prescription', 'p')
                ->innerJoin('p.consultation', 'c')
                ->innerJoin('c.patient', 'pt')
                ->andWhere('c.personnelMedical = :personnel')
                ->andWhere('a.doneAt >= :since')
                ->setParameter('personnel', $personnel)
                ->setParameter('since', $since)
                ->orderBy('a.doneAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (TableNotFoundException) {
            return [];
        }
    }
}
