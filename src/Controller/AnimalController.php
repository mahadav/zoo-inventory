<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\DietItem;
use App\Entity\AnimalCategory;
use App\Entity\AnimalCount;
use App\Repository\AnimalRepository;
use App\Repository\AnimalCategoryRepository;
use App\Repository\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/animals')]
class AnimalController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnimalRepository $animalRepository,
        private AnimalCategoryRepository $categoryRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'animal_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['animalName']) || !isset($data['scientificName']) || !isset($data['categoryId'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Find category
        $category = $this->categoryRepository->find($data['categoryId']);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        // Create new animal
        $animal = new Animal();
        $animal->setAnimalName($data['animalName']);
        $animal->setScientificName($data['scientificName']);
        $animal->setCategory($category);

        // Handle current stock if provided
        if (isset($data['currentStock'])) {
            $stockData = $data['currentStock'];
            $currentStock =  new AnimalCount();

            if (isset($stockData['count'])) {
                $currentStock->setTotal($stockData['count']);
            }
            if (isset($stockData['femaleCount'])) {
                $currentStock->setFemale($stockData['femaleCount']);
            }
            if (isset($stockData['maleCount'])) {
                $currentStock->setMale($stockData['maleCount']);
            }
            if (isset($stockData['underageCount'])) {
                $currentStock->setUnderAge($stockData['underageCount']);
            }
            if (isset($stockData['lastCountDate'])) {
                $currentStock->setLastCountDate(new \DateTime($stockData['lastCountDate']));
            }

            $animal->setCurrentStock($currentStock);
        }

        // Validate the animal
        $errors = $this->validator->validate($animal);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Persist the animal
        $this->entityManager->persist($animal);
        $this->entityManager->flush();

        return $this->json($animal, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'animal_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $animal = $this->animalRepository->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update basic fields if provided
        if (isset($data['animalName'])) {
            $animal->setAnimalName($data['animalName']);
        }

        if (isset($data['scientificName'])) {
            $animal->setScientificName($data['scientificName']);
        }

        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category) {
                return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }
            $animal->setCategory($category);
        }

        // Update current stock if provided
        if (isset($data['currentStock'])) {
            $stockData = $data['currentStock'];
            $currentStock = $animal->getCurrentStock() ?? new AnimalCount();

            if (isset($stockData['count'])) {
                $currentStock->setTotal($stockData['count']);
            }
            if (isset($stockData['femaleCount'])) {
                $currentStock->setFemale($stockData['femaleCount']);
            }
            if (isset($stockData['maleCount'])) {
                $currentStock->setMale($stockData['maleCount']);
            }
            if (isset($stockData['underageCount'])) {
                $currentStock->setUnderAge($stockData['underageCount']);
            }
            if (isset($stockData['lastCountDate'])) {
                $currentStock->setLastCountDate(new \DateTime($stockData['lastCountDate']));
            }

            $animal->setCurrentStock($currentStock);
        }


        $errors = $this->validator->validate($animal);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($animal);
    }

    /**
     * Unified endpoint for creating/updating animal diet
     *
     * POST/PUT /api/animals/{id}/diet
     *
     * Replaces all existing diet items with the new ones provided
     */
    #[Route('/{id}/diet', name: 'animal_diet_create_update', methods: ['POST', 'PUT'])]
    public function createOrUpdateDiet(Request $request, int $id, ValidatorInterface $validator, FeedItemRepository $fr): JsonResponse
    {
        $animal = $this->animalRepository->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Validate request contains dietItems array
        if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
            return $this->json(
                ['error' => 'Request must contain a dietItems array'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Clear existing diet items
        foreach ($animal->getDietItems() as $existingDietItem) {
            $this->entityManager->remove($existingDietItem);
        }

        // Add and validate new diet items
        $dietItems = [];
        foreach ($data['dietItems'] as $index => $dietItemData) {
            if (empty($dietItemData['feedId']) || empty($dietItemData['quantity'])) {
                return $this->json(
                    ['error' => "Diet item at index $index is missing required fields (feedId, quantity)"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $dietItem = new DietItem();

            $feedItem = $fr->find($dietItemData['feedId']);

            if (!$feedItem) {
                throw new \Exception("Feed item not found: " . $dietItemData['feedId']);
            }

            $dietItem->setFeedItem($feedItem);

            $dietItem->setQuantity($dietItemData['quantity']);
            $dietItem->setAnimal($animal);
            $dietItem->setAdultCount($animal->getCurrentStock()->getMale()+$animal->getCurrentStock()->getFemale());

            // Validate the diet item
            $errors = $validator->validate($dietItem);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $dietItems[] = $dietItem;
        }

        // Persist all new diet items
        foreach ($dietItems as $dietItem) {
            $this->entityManager->persist($dietItem);
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => count($dietItems) . ' diet items created/updated',
            'animal' => $animal
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'animal_get', methods: ['GET'])]
    public function getAnimal(int $id): JsonResponse
    {
        $animal = $this->animalRepository->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($animal);
    }

    #[Route('', name: 'animal_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $animals = $this->animalRepository->findAll();
        return $this->json($animals);
    }

    #[Route('/{animalId}/diet-items', name: 'animal_add_diet_items', methods: ['POST'])]
    public function addDietItems(Request $request, int $animalId): JsonResponse
    {
        $animal = $this->animalRepository->find($animalId);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Validate request contains dietItems array
        if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
            return $this->json(
                ['error' => 'Request must contain a dietItems array'],
                Response::HTTP_BAD_REQUEST
            );
        }




        $dietItems = [];
        foreach ($data['dietItems'] as $index => $dietItemData) {
            // Validate required fields
            if (!isset($dietItemData['feedItemId']) || !isset($dietItemData['quantity'])) {
                return $this->json(
                    ['error' => "Diet item at index $index is missing required fields (feedItemId, quantity)"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Find the feed item
            $feedItem = $this->entityManager->getRepository(FeedItem::class)->find($dietItemData['feedItemId']);
            if (!$feedItem) {
                return $this->json(
                    ['error' => "FeedItem not found for diet item at index $index"],
                    Response::HTTP_NOT_FOUND
                );
            }

            // Validate quantity is positive
            if ($dietItemData['quantity'] <= 0) {
                return $this->json(
                    ['error' => "Quantity must be positive for diet item at index $index"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // First, remove all existing diet items for this animal
            $existingDietItems = $animal->getDietItems();
            foreach ($existingDietItems as $existingDietItem) {
                $this->entityManager->remove($existingDietItem);
            }

            // Create new diet item
            $dietItem = new DietItem();
            $dietItem->setFeedItem($feedItem);
            $dietItem->setQuantity($dietItemData['quantity']);
            $dietItem->setAnimal($animal);

            // Validate the diet item
            $errors = $this->validator->validate($dietItem);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($dietItem);
            $dietItems[] = $dietItem;
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => count($dietItems) . ' diet items added',
            'dietItems' => $dietItems
        ], Response::HTTP_CREATED);
    }
}