<?php

namespace App\Controller;

use App\Entity\AnimalPopulation;
use App\Entity\AnimalSpecies;
use App\Entity\DietItem;
use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
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
        private AnimalSpeciesRepository $animalSpeciesRepository,
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
        if (!isset($data['animalName'], $data['scientificName'], $data['categoryId'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Find category
        $category = $this->categoryRepository->find($data['categoryId']);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        // Create AnimalSpecies entity
        $animalSpecies = new AnimalSpecies();
        $animalSpecies->setCommonName($data['animalName']);
        $animalSpecies->setScientificName($data['scientificName']);
        $animalSpecies->setCategory($category);
        $animalSpecies->setSchedule($data['schedule'] ?? null);

        $errors = $this->validator->validate($animalSpecies);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($animalSpecies);
        $this->entityManager->flush();

        return $this->json($animalSpecies, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'animal_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $animalSpecies = $this->animalSpeciesRepository->find($id);
        if (!$animalSpecies) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['animalName'])) {
            $animalSpecies->setCommonName($data['animalName']);
        }
        if (isset($data['scientificName'])) {
            $animalSpecies->setScientificName($data['scientificName']);
        }
        if (isset($data['schedule'])) {
            $animalSpecies->setSchedule($data['schedule']);
        }
        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category) {
                return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }
            $animalSpecies->setCategory($category);
        }

        $errors = $this->validator->validate($animalSpecies);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($animalSpecies);
    }

    /**
     * Replaces all existing diet items for an animal with the new ones provided.
     */
    #[Route('/{id}/diet', name: 'animal_diet_replace', methods: ['POST', 'PUT'])]
    public function replaceDiet(
        Request $request,
        int $id,
        FeedItemRepository $feedRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $animalSpecies = $this->animalSpeciesRepository->find($id);
        if (!$animalSpecies) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
            return $this->json(['error' => 'dietItems array is required'], Response::HTTP_BAD_REQUEST);
        }

        // Remove old diet items
        foreach ($animalSpecies->getDietItems() as $existingItem) {
            $this->entityManager->remove($existingItem);
        }

        $newDietItems = [];

        foreach ($data['dietItems'] as $index => $dietItemData) {
            if (empty($dietItemData['feedId']) || empty($dietItemData['quantity'])) {
                return $this->json([
                    'error' => "Diet item at index $index is missing required fields (feedId, quantity)"
                ], Response::HTTP_BAD_REQUEST);
            }

            $feedItem = $feedRepo->find($dietItemData['feedId']);
            if (!$feedItem) {
                return $this->json([
                    'error' => "Feed item not found for ID " . $dietItemData['feedId']
                ], Response::HTTP_NOT_FOUND);
            }



            $animalPopulationRepo=$em->getRepository(AnimalPopulation::class);
            $animalPopulation = $animalPopulationRepo->findOneBy(
                ['species' => $animalSpecies],
                ['recordedAt' => 'DESC']                      // limit to 1 record
            );

            if ($animalPopulation) {
                $animalCount = $animalPopulation->getClosing();
                $totalFeedingAnimal = $animalCount->getMale() + $animalCount->getFemale();
            } else {
                $totalFeedingAnimal = 0;
            }

            $dietItem = new DietItem();
            $dietItem->setFeedItem($feedItem);
            $dietItem->setQuantity($dietItemData['quantity']);
            $dietItem->setSpecies($animalSpecies);
            $dietItem->setAdultCount($totalFeedingAnimal);

            $errors = $this->validator->validate($dietItem);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($dietItem);
            $newDietItems[] = $dietItem;
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => count($newDietItems) . ' diet items replaced',
            'animalId' => $animalSpecies->getId(),
        ]);
    }

    #[Route('/{id}', name: 'animal_get', methods: ['GET'])]
    public function getAnimal(int $id): JsonResponse
    {
        $animal = $this->animalSpeciesRepository->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($animal);
    }

    #[Route('', name: 'animal_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $animals = $this->animalSpeciesRepository->findAll();
        return $this->json($animals);
    }
}
