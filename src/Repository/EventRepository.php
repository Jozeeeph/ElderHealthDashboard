<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    // ✅ NOUVEAU : récupère Event + participations + utilisateur (participant)
    public function findOneWithParticipations(int $id): ?Event
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.participations', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')   // 🔁 change "utilisateur" si ton champ s’appelle autrement
            ->leftJoin('e.type', 't')->addSelect('t')          // optionnel : si tu veux aussi le type sans lazy
            ->andWhere('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findEventsToRemind(\DateTimeImmutable $now): array
    {
        $to = $now->modify('+24 hours');

        return $this->createQueryBuilder('e')
            ->leftJoin('e.participations', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->andWhere('e.dateDebut BETWEEN :now AND :to')
            ->andWhere('e.reminderSent = false')
            ->andWhere('e.statut = :s')
            ->setParameter('s', 'PUBLIE')
            ->setParameter('now', $now)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }


}
