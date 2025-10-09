<?php


namespace App\Repository;

use App\Entity\Animal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Animal>
 *
 * @method Animal|null find($id, $lockMode = null, $lockVersion = null)
 * @method Animal|null findOneBy(array $criteria, array $orderBy = null)
 * @method Animal[]    findAll()
 * @method Animal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnimalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Animal::class);
    }

    public function getAnimalsWithDietForAdults(): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.dietItems', 'd')
            ->addSelect('d')
            ->getQuery();

        $animals = $qb->getResult();

        $result = [];

        foreach ($animals as $animal) {
            $count = $animal->getCurrentStock();
            $adultCount = ($count->getMale() ?? 0) + ($count->getFemale() ?? 0);

            //dd($adultCount);die;
            $dietData = [];
            foreach ($animal->getDietItems() as $dietItem) {
                $dietData[] = [
                    'feedItem' => $dietItem->getFeedItem()->getId(), // or name
                    'unitQuantity' => $dietItem->getQuantity(),
                    'totalQuantity' => $dietItem->getQuantity() * $adultCount
                ];
            }


            $result[] = [
                'animalId' => $animal->getId(),
                'animalName' => $animal->getAnimalName(),
                'adultCount' => $adultCount,
                'dietItems' => $dietData
            ];
        }

        return $result;
    }
}
