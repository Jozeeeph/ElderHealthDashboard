<?php

namespace App\Repository;

use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
     * Get equipment for listing (without loading relationships)
     * Prevents circular reference issues in Twig
     */
    public function findAllForListing(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id', 'e.nom', 'e.description', 'e.prix', 
                     'e.quantiteDisponible', 'e.statut', 'e.categorie', 'e.image')
            ->orderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchForFront(string $query, ?string $category, array $orderBy): array
    {
        $qb = $this->createQueryBuilder('e');
        $normalizedQuery = mb_strtolower(trim($query));

        if ($normalizedQuery !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(e.nom) LIKE :query',
                    'LOWER(e.description) LIKE :query',
                    'LOWER(e.categorie) LIKE :query'
                )
            )->setParameter('query', '%'.$normalizedQuery.'%');
        }

        if ($category !== null && $category !== '') {
            $qb->andWhere('e.categorie = :category')
                ->setParameter('category', $category);
        }

        $field = array_key_first($orderBy) ?? 'id';
        $direction = strtoupper((string) ($orderBy[$field] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy('e.'.$field, $direction);

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Equipement[] Returns an array of Equipement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Equipement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
