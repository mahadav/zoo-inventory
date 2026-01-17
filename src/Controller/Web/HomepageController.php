<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/')]
class HomepageController extends AbstractController
{
    #[Route('/home', name: 'web_homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        // You can add any dashboard statistics data here later
        return $this->render('index.html.twig', [
            'page_title' => 'Dashboard',
        ]);
    }



    #[Route('/', name: 'web_landing', methods: ['GET'])]
    public function landingPage(): Response
    {
        return $this->render('landing.html.twig', [
            'page_title' => 'Zoo Inventory Management',
        ]);
    }

    #[Route('/dashboard', name: 'web_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Placeholder for dashboard statistics
        // You can inject services here later to get actual statistics
        $stats = [
            'total_animals' => 0,
            'total_foods' => 0,
            'total_categories' => 0,
            'recent_feeds' => 0,
        ];

        return $this->render('dashboard.html.twig', [
            'page_title' => 'Dashboard',
            'stats' => $stats,
        ]);
    }
}