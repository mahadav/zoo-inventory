<?php

namespace App\Controller\Web;

use App\Entity\FeedItem;
use App\Repository\FeedItemRepository;
use App\Repository\FeedUnitRepository;
use App\Repository\FeedCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\AnimalCategoryRepository;


#[Route('/web/feed-items')]
class FeedItemController extends AbstractController
{
    #[Route('', name: 'web_feed_item_index', methods: ['GET'])]
    public function index(FeedItemRepository $feedItemRepository): Response
    {
        return $this->render('feed_item/index.html.twig', [
            'feedItems' => $feedItemRepository->findAll()
        ]);
    }

    #[Route('/new', name: 'web_feed_item_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FeedUnitRepository $unitRepository,
        FeedCategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AnimalCategoryRepository $animalCategoryRepository,
    ): Response {
        $feedItem = new FeedItem();

        if ($request->isMethod('POST')) {
            $feedItem->setName($request->request->get('name'));
            $feedItem->setEstimatedPrice($request->request->get('estimatedPrice'));

            $unit = $unitRepository->find($request->request->get('unitId'));
            $category = $categoryRepository->find($request->request->get('categoryId'));
            $animalCategory = $animalCategoryRepository->find($request->request->get('animalCategoryId'));
            $feedItem->setAnimalCategory($animalCategory);


            if (!$unit || !$category) {
                $this->addFlash('danger', 'Invalid Unit or Category');
                return $this->redirectToRoute('web_feed_item_new');
            }

            $feedItem->setUnit($unit);
            $feedItem->setCategory($category);

            $errors = $validator->validate($feedItem);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error->getMessage());
                }
            } else {
                $entityManager->persist($feedItem);
                $entityManager->flush();

                $this->addFlash('success', 'Feed item created successfully!');
                return $this->redirectToRoute('web_feed_item_index');
            }
        }

        return $this->render('feed_item/new.html.twig', [
            'feedItem' => $feedItem,
            'units' => $unitRepository->findAll(),
            'categories' => $categoryRepository->findAll(),
            'isEdit' => false,
            'animalCategories' => $animalCategoryRepository->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'web_feed_item_edit', methods: ['GET', 'POST'])]
    public function edit(
        FeedItem $feedItem,
        Request $request,
        FeedUnitRepository $unitRepository,
        FeedCategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AnimalCategoryRepository $animalCategoryRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            $feedItem->setName($request->request->get('name'));
            $feedItem->setEstimatedPrice($request->request->get('estimatedPrice'));
            $animalCategory = $animalCategoryRepository->find($request->request->get('animalCategoryId'));
            $feedItem->setAnimalCategory($animalCategory);

            $unit = $unitRepository->find($request->request->get('unitId'));
            $category = $categoryRepository->find($request->request->get('categoryId'));

            if (!$unit || !$category) {
                $this->addFlash('danger', 'Invalid Unit or Category');
                return $this->redirectToRoute('web_feed_item_edit', ['id' => $feedItem->getId()]);
            }

            $feedItem->setUnit($unit);
            $feedItem->setCategory($category);

            $errors = $validator->validate($feedItem);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error->getMessage());
                }
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Feed item updated successfully!');
                return $this->redirectToRoute('web_feed_item_index');
            }
        }

        return $this->render('feed_item/edit.html.twig', [
            'feedItem' => $feedItem,
            'units' => $unitRepository->findAll(),
            'categories' => $categoryRepository->findAll(),
            'animalCategories' => $animalCategoryRepository->findAll(),
            'isEdit' => true
        ]);
    }
}
