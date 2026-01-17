<?php

namespace App\Repository;

use App\Entity\FeedItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeedItem>
 *
 * @method FeedItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method FeedItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method FeedItem[]    findAll()
 * @method FeedItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FeedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedItem::class);
    }

    public function save(FeedItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FeedItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // Example custom method to find items by price range
    public function findByPriceRange(int $minPrice, int $maxPrice): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.estimatedPrice >= :minPrice')
            ->andWhere('f.estimatedPrice <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('f.estimatedPrice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Example custom method to find items by category
   /* public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.category', 'c')
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }*/

    public function findByCategory(int $categoryId): array
    {
        // Assuming FeedItem has a relationship with AnimalCategory
        return $this->createQueryBuilder('f')
            ->join('f.animalCategory', 'c')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}