<?php

namespace App\Repository;

use App\Entity\AnimalCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnimalCategory>
 *
 * @method AnimalCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method AnimalCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method AnimalCategory[]    findAll()
 * @method AnimalCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnimalCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalCategory::class);
    }

    public function findAnimalsByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'a')
            ->leftJoin('c.animals', 'a')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getOneOrNullResult()
            ?->getAnimals()
            ->toArray() ?? [];
    }

    // Add custom query helpers here when you need them.
}
