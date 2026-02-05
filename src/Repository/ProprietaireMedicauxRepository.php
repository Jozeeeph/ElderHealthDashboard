<?php

namespace App\Repository;

use App\Entity\ProprietaireMedicaux;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProprietaireMedicauxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProprietaireMedicaux::class);
    }
}
