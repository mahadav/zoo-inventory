<?php

namespace App\Controller;

use App\Util\FastingDays;
use App\Entity\AnimalCategory;
use App\Entity\FeedCategory;
use App\Entity\FeedItem;
use App\Entity\FeedUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConfigurationController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/configuration', name: 'app_configuration', methods: ['GET'])]
    public function getConfiguration(): JsonResponse
    {
        $feedUnits = $this->entityManager->getRepository(FeedUnit::class)->findAll();
        $feedItems = $this->entityManager->getRepository(FeedItem::class)->findAll();
        $animalCategories = $this->entityManager->getRepository(AnimalCategory::class)->findAll();
        $feedCategories = $this->entityManager->getRepository(FeedCategory::class)->findAll();

        return $this->json([
            'feedUnits' => $feedUnits,
            'feedItems' => $feedItems,
            'animalCategories' => $animalCategories,
            'feedCategories' => $feedCategories,
            'weekDays' => FastingDays::DAYS
        ]);
    }

    #[Route('/api/configuration/feed-items', name: 'app_configuration_feed_items', methods: ['GET'])]
    public function getFeedItems(): JsonResponse
    {
        $feedItems = $this->entityManager->getRepository(FeedItem::class)->findAll();
        return $this->json($feedItems);
    }
}