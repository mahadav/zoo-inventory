<?php

namespace App\Controller;

use App\Entity\DietItem;
use App\Repository\AnimalSpeciesRepository;
use App\Repository\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/diet-items')]
class DietItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnimalSpeciesRepository $animalSpeciesRepository,
        private FeedItemRepository $feedItemRepository,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * List all diet items for a specific animal
     */
    #[Route('/animal/{animalId}', name: 'diet_items_list_by_animal', methods: ['GET'])]
    public function listByAnimal(int $animalId): JsonResponse
    {
        $animal = $this->animalSpeciesRepository->find($animalId);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($animal->getDietItems());
    }

    /**
     * Add new diet items for a specific animal (one or multiple)
     */
    #[Route('/animal/{animalId}', name: 'diet_items_add', methods: ['POST'])]
    public function add(Request $request, int $animalId): JsonResponse
    {
        $animal = $this->animalSpeciesRepository->find($animalId);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
            return $this->json(['error' => 'dietItems array is required'], Response::HTTP_BAD_REQUEST);
        }

        $addedItems = [];

        foreach ($data['dietItems'] as $index => $dietItemData) {
            if (empty($dietItemData['feedId']) || empty($dietItemData['quantity'])) {
                return $this->json([
                    'error' => "Diet item at index $index is missing required fields (feedId, quantity)"
                ], Response::HTTP_BAD_REQUEST);
            }

            $feedItem = $this->feedItemRepository->find($dietItemData['feedId']);
            if (!$feedItem) {
                return $this->json(['error' => "Feed item not found for ID " . $dietItemData['feedId']], Response::HTTP_NOT_FOUND);
            }

            $dietItem = new DietItem();
            $dietItem->setFeedItem($feedItem);
            $dietItem->setQuantity($dietItemData['quantity']);
            $dietItem->setAnimal($animal);
            $dietItem->setAdultCount($animal->getCurrentStock()->getFeedEligible());

            $errors = $this->validator->validate($dietItem);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($dietItem);
            $addedItems[] = $dietItem;
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => count($addedItems) . ' diet items added successfully',
            'items' => $addedItems
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a specific diet item
     */

    #[Route('/{id}', name: 'diet_item_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $dietItem = $this->entityManager->getRepository(DietItem::class)->find($id);
        if (!$dietItem) {
            return $this->json(['error' => 'Diet item not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['quantity'])) {
            if ($data['quantity'] <= 0) {
                return $this->json(['error' => 'Quantity must be greater than zero'], Response::HTTP_BAD_REQUEST);
            }
            $dietItem->setQuantity($data['quantity']);
        }

        if (isset($data['feedId'])) {
            $feedItem = $this->feedItemRepository->find($data['feedId']);
            if (!$feedItem) {
                return $this->json(['error' => 'Feed item not found'], Response::HTTP_NOT_FOUND);
            }
            $dietItem->setFeedItem($feedItem);
        }

        $errors = $this->validator->validate($dietItem);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Diet item updated successfully',
            'dietItem' => $dietItem
        ]);
    }

    /**
     * Delete a diet item
     */
    #[Route('/{id}', name: 'diet_item_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $dietItem = $this->entityManager->getRepository(DietItem::class)->find($id);
        if (!$dietItem) {
            return $this->json(['error' => 'Diet item not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($dietItem);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Diet item deleted successfully'
        ], Response::HTTP_OK);
    }
}
