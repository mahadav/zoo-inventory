<?php

namespace App\Service;

use App\Entity\DietItem;
use App\Repository\AnimalSpeciesRepository;
use App\Repository\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;

class DietService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnimalSpeciesRepository $animalSpeciesRepository,
        private FeedItemRepository $feedItemRepository,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Get diet items for a specific animal
     */
    public function getDietItemsByAnimal(int $animalId): array
    {
        $animal = $this->animalSpeciesRepository->find($animalId);
        if (!$animal) {
            throw new \Exception('Animal not found', Response::HTTP_NOT_FOUND);
        }

        return $animal->getDietItems()->toArray();
    }

    /**
     * Add new diet items for a specific animal
     */
    public function addDietItems(int $animalId, array $data): array
    {
        $animal = $this->animalSpeciesRepository->find($animalId);
        if (!$animal) {
            throw new \Exception('Animal not found', Response::HTTP_NOT_FOUND);
        }

        if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
            throw new \Exception('dietItems array is required', Response::HTTP_BAD_REQUEST);
        }

        $addedItems = [];

        foreach ($data['dietItems'] as $index => $dietItemData) {
            if (empty($dietItemData['feedId']) || empty($dietItemData['quantity'])) {
                throw new \Exception(
                    "Diet item at index $index is missing required fields (feedId, quantity)",
                    Response::HTTP_BAD_REQUEST
                );
            }

            $feedItem = $this->feedItemRepository->find($dietItemData['feedId']);
            if (!$feedItem) {
                throw new \Exception(
                    "Feed item not found for ID " . $dietItemData['feedId'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $dietItem = new DietItem();
            $dietItem->setFeedItem($feedItem);
            $dietItem->setQuantity($dietItemData['quantity']);
            $dietItem->setSpecies($animal);
            // $dietItem->setAdultCount($animal->getCurrentStock()->getFeedEligible());

            $errors = $this->validator->validate($dietItem);
            if (count($errors) > 0) {
                throw new \Exception((string) $errors, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($dietItem);
            $addedItems[] = $dietItem;
        }

        $this->entityManager->flush();

        return $addedItems;
    }

    /**
     * Update a specific diet item
     */
    public function updateDietItem(int $id, array $data): DietItem
    {
        $dietItem = $this->entityManager->getRepository(DietItem::class)->find($id);
        if (!$dietItem) {
            throw new \Exception('Diet item not found', Response::HTTP_NOT_FOUND);
        }

        if (isset($data['quantity'])) {
            if ($data['quantity'] <= 0) {
                throw new \Exception('Quantity must be greater than zero', Response::HTTP_BAD_REQUEST);
            }
            $dietItem->setQuantity($data['quantity']);
        }

        if (isset($data['feedId'])) {
            $feedItem = $this->feedItemRepository->find($data['feedId']);
            if (!$feedItem) {
                throw new \Exception('Feed item not found', Response::HTTP_NOT_FOUND);
            }
            $dietItem->setFeedItem($feedItem);
        }

        $errors = $this->validator->validate($dietItem);
        if (count($errors) > 0) {
            throw new \Exception((string) $errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $dietItem;
    }

    /**
     * Delete a diet item
     */
    public function deleteDietItem(int $id): void
    {
        $dietItem = $this->entityManager->getRepository(DietItem::class)->find($id);
        if (!$dietItem) {
            throw new \Exception('Diet item not found', Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($dietItem);
        $this->entityManager->flush();
    }

    /**
     * Get all animal species for dropdowns
     */
    public function getAllAnimalSpecies(): array
    {
        return $this->animalSpeciesRepository->findAll();
    }

    /**
     * Get all feed items for dropdowns
     */
    public function getAllFeedItems(): array
    {
        return $this->feedItemRepository->findAll();
    }
}