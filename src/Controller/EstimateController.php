<?php

namespace App\Controller;

use App\Entity\AnimalPopulation;
use App\Repository\DietItemRepository;
use App\Repository\FeedItemRepository;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EstimateController extends AbstractController
{
    #[Route('/api/estimate/feed', name: 'estimate_feed', methods: ['POST'])]
    public function estimateFeedConsumption(
        Request $request,
        FeedItemRepository $fir,
        DietItemRepository $dietItemRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['fasting_day'], $data['month'], $data['year'])) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $fastingDay = (int) $data['fasting_day'];
        $month = (int) $data['month'];
        $year = (int) $data['year'];

        // Calculate feeding days
        if ($month === -1) {
            $totalDays = $feedingDays = 30;
        } else {
            $totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $feedingDays = ($fastingDay === -1)
                ? $totalDays
                : $totalDays - $this->countWeekdaysInMonth($fastingDay, $month, $year);
        }

        // Use new repository method to get aggregated feed consumption per day
        //$animalPopulation=entitymanager->getRepository(AnimalPopulation::class);
        $dailyFeedTotals = $dietItemRepository->getDailyFeedConsumption(); // ← New method

        $estimates = [];
        $totalPrice = 0;

        foreach ($dailyFeedTotals as $item) {
            $feedItemId = $item['feedItemId'];
            $feedItem = $fir->find($feedItemId);

            if (!$feedItem) continue;

            $quantityPerDay = $item['totalQuantity'];
            $totalQuantity = $quantityPerDay * $feedingDays;
            $pricePerUnit = $feedItem->getEstimatedPrice();
            $itemPrice = $totalQuantity * $pricePerUnit;

            $estimates[] = [
                'id' => $feedItem->getId(),
                'name' => $feedItem->getName(),
                'unit' => $feedItem->getUnit()->getName(),
                'quantity_per_day' => round($quantityPerDay, 2),
                'total_quantity' => round($totalQuantity, 2),
                'price_per_unit' => $pricePerUnit,
                'total_price' => round($itemPrice, 2),
            ];

            $totalPrice += $itemPrice;
        }

        return $this->json([
            'month' => $month,
            'year' => $year,
            'total_days' => $totalDays,
            'feeding_days' => $feedingDays,
            'fasting_days' => $totalDays - $feedingDays,
            'estimates' => $estimates,
            'total_price' => round($totalPrice, 2),
            'currency' => 'INR',
        ]);
    }

    private function countWeekdaysInMonth(int $weekday, int $month, int $year): int
    {
        $weekday = $weekday % 7; // PHP: Sunday = 0, Saturday = 6
        $count = 0;
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = new \DateTime("$year-$month-$day");
            if ((int) $date->format('w') === $weekday) {
                $count++;
            }
        }

        return $count;
    }

    #[Route('/api/estimate/generate-supply-order', name: 'generate_supply_order', methods: ['POST'])]
    public function generateSupplyOrder(
        Request $request,
        FeedItemRepository $fir,
        DietItemRepository $dietItemRepository
    ): Response {
        $data = json_decode($request->getContent(), true);

        // Validate required parameters
        $requiredParams = [
            'file_number', 'memo_number', 'date',
            'supplier_name', 'supplier_address', 'month',
            'year', 'terms_conditions', 'fasting_day'
        ];

        foreach ($requiredParams as $param) {
            if (!isset($data[$param])) {
                return $this->json(['error' => "Missing required parameter: $param"], 400);
            }
        }

        // Create request parameters array instead of JSON string
        $requestParams = [
            'fasting_day' => $data['fasting_day'],
            'month' => $data['month'],
            'year' => $data['year']
        ];

        // Create a new request with the parameters
        $newRequest = new Request([], $requestParams, [], [], [], [], json_encode($requestParams));
        $newRequest->headers->set('Content-Type', 'application/json');

        // Get feed estimates
        $estimateData = $this->estimateFeedConsumption(
            $newRequest,
            $fir,
            $dietItemRepository
        )->getContent();

        $estimateData = json_decode($estimateData, true);

        // Generate HTML for PDF
        $html = $this->renderView('supply_order.html.twig', [
            'file_number' => $data['file_number'],
            'memo_number' => $data['memo_number'],
            'date' => $data['date'],
            'supplier_name' => $data['supplier_name'],
            'supplier_address' => $data['supplier_address'],
            'month' => $data['month'],
            'year' => $data['year'],
            'month_name' => date('F', mktime(0, 0, 0, $data['month'], 10)),
            'terms_conditions' => $data['terms_conditions'],
            'estimates' => $estimateData['estimates'],
            'feeding_days' => $estimateData['feeding_days']
        ]);

        // Configure Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Return PDF as response
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="supply_order.pdf"'
            ]
        );
    }

    #[Route('/api/estimate/generate-pricing-estimate', name: 'generate_pricing_estimate', methods: ['POST'])]
    public function generatePricingEstimate(
        Request $request,
        FeedItemRepository $fir,
        DietItemRepository $dietItemRepository
    ): Response {
        $data = json_decode($request->getContent(), true);

        // Validate required parameters
        if (!isset($data['month'], $data['year'], $data['fasting_day'])) {
            return $this->json(['error' => 'Missing required parameters: month, year, fasting_day'], 400);
        }

        // Create request parameters
        $requestParams = [
            'fasting_day' => $data['fasting_day'],
            'month' => $data['month'],
            'year' => $data['year']
        ];

        // Create a new request with the parameters
        $newRequest = new Request([], $requestParams, [], [], [], [], json_encode($requestParams));
        $newRequest->headers->set('Content-Type', 'application/json');

        // Get feed estimates
        $estimateData = $this->estimateFeedConsumption(
            $newRequest,
            $fir,
            $dietItemRepository
        )->getContent();

        $estimateData = json_decode($estimateData, true);

        // Get day name from fasting day number
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $fastingDayName = $data['fasting_day'] === -1 ? 'No fasting day' : $days[$data['fasting_day']];

        // Generate HTML for PDF
        $html = $this->renderView('pricing_estimate.html.twig', [
            'date_generated' => date('d/m/Y'),
            'month_name' => date('F', mktime(0, 0, 0, $data['month'], 10)),
            'year' => $data['year'],
            'estimates' => $estimateData['estimates'],
            'total_price' => $estimateData['total_price'],
            'currency' => $estimateData['currency'],
            'feeding_days' => $estimateData['feeding_days'],
            'total_days' => $estimateData['total_days'],
            'fasting_days' => $estimateData['fasting_days'],
            'fasting_day_name' => $fastingDayName,
        ]);

        // Configure Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Return PDF as response
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="pricing_estimate.pdf"'
            ]
        );
    }
}
