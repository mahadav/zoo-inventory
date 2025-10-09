<?php

namespace App\Controller;

use App\Entity\FeedItem;
use App\Repository\FeedUnitRepository;
use App\Repository\FeedCategoryRepository;
use App\Repository\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/feed-items')]
class FeedController extends AbstractController
{
    #[Route('', name: 'feed_item_create', methods: ['POST'])]
    public function create(
        Request $request,
        FeedUnitRepository $unitRepository,
        FeedCategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['name']) || !isset($data['estimatedPrice']) || !isset($data['unitId']) || !isset($data['categoryId'])) {
            return $this->json(['error' => 'Missing required fields (name, estimatedPrice, unitId, categoryId)'], Response::HTTP_BAD_REQUEST);
        }

        // Find unit and category
        $unit = $unitRepository->find($data['unitId']);
        $category = $categoryRepository->find($data['categoryId']);

        if (!$unit) {
            return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        // Create new feed item
        $feedItem = new FeedItem();
        $feedItem->setName($data['name']);
        $feedItem->setEstimatedPrice($data['estimatedPrice']);
        $feedItem->setUnit($unit);
        $feedItem->setCategory($category);

        // Validate the entity
        $errors = $validator->validate($feedItem);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Persist and flush
        $entityManager->persist($feedItem);
        $entityManager->flush();

        return $this->json($feedItem, Response::HTTP_CREATED);
    }

    #[Route('', name: 'feed_item_list', methods: ['GET'])]
    public function list(FeedItemRepository $feedItemRepository): JsonResponse
    {
        $feedItems = $feedItemRepository->findAll();
        return $this->json($feedItems);
    }

    #[Route('/{id}', name: 'feed_item_show', methods: ['GET'])]
    public function show(FeedItem $feedItem): JsonResponse
    {
        return $this->json($feedItem);
    }

    #[Route('/{id}', name: 'feed_item_update', methods: ['PUT', 'PATCH'])]
    public function update(
        Request $request,
        FeedItem $feedItem,
        FeedUnitRepository $unitRepository,
        FeedCategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Update fields if they exist in the request
        if (isset($data['name'])) {
            $feedItem->setName($data['name']);
        }

        if (isset($data['estimatedPrice'])) {
            $feedItem->setEstimatedPrice($data['estimatedPrice']);
        }

        if (isset($data['unitId'])) {
            $unit = $unitRepository->find($data['unitId']);
            if (!$unit) {
                return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
            }
            $feedItem->setUnit($unit);
        }

        if (isset($data['categoryId'])) {
            $category = $categoryRepository->find($data['categoryId']);
            if (!$category) {
                return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }
            $feedItem->setCategory($category);
        }

        // Validate the entity
        $errors = $validator->validate($feedItem);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        return $this->json($feedItem);
    }
}