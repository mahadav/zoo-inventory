<?php

namespace App\Controller;

use App\Service\EstimateService;
use App\Service\PdfGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class EstimateController extends AbstractController
{
    private EstimateService $estimateService;
    private PdfGeneratorService $pdfGeneratorService;

    public function __construct(
        EstimateService $estimateService,
        PdfGeneratorService $pdfGeneratorService
    ) {
        $this->estimateService = $estimateService;
        $this->pdfGeneratorService = $pdfGeneratorService;
    }

    #[Route('/api/estimate/feed', name: 'estimate_feed', methods: ['POST'])]
    public function estimateFeedConsumption(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate request using service
        $validationError = $this->estimateService->validateFeedEstimationRequest($data);
        if ($validationError) {
            return $this->json(['error' => $validationError], 400);
        }

        $fastingDay = (int) $data['fasting_day'];
        $month = (int) $data['month'];
        $year = (int) $data['year'];

        // Generate estimates using service
        $estimates = $this->estimateService->generateFeedEstimates($fastingDay, $month, $year);

        return $this->json($estimates);
    }

    #[Route('/api/estimate/generate-supply-order', name: 'generate_supply_order', methods: ['POST'])]
    public function generateSupplyOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate request using service
        $validationError = $this->estimateService->validateSupplyOrderRequest($data);
        if ($validationError) {
            return $this->json(['error' => $validationError], 400);
        }

        // Generate feed estimates
        $estimateData = $this->estimateService->generateFeedEstimates(
            (int) $data['fasting_day'],
            (int) $data['month'],
            (int) $data['year']
        );

        // Prepare data for PDF generation
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/logo.png';

        try {
            $response = $this->pdfGeneratorService->generateSupplyOrderPdf(
                $data,
                $logoPath,
                $estimateData['estimates']
            );

            // For PDF response, we need to return it directly
            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/estimate/generate-pricing-estimate', name: 'generate_pricing_estimate', methods: ['POST'])]
    public function generatePricingEstimate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required parameters
        $validationError = $this->estimateService->validateFeedEstimationRequest($data);
        if ($validationError) {
            return $this->json(['error' => $validationError], 400);
        }

        // Generate feed estimates
        $estimateData = $this->estimateService->generateFeedEstimates(
            (int) $data['fasting_day'],
            (int) $data['month'],
            (int) $data['year']
        );

        // Add fasting day name to data
        $data['fasting_day_name'] = $this->estimateService->getFastingDayName((int) $data['fasting_day']);

        try {
            $response = $this->pdfGeneratorService->generatePricingEstimatePdf(
                $data,
                $estimateData
            );

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/estimate/feeding-days', name: 'estimate_feeding_days', methods: ['POST'])]
    public function getFeedingDays(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['fasting_day'], $data['month'], $data['year'])) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $daysInfo = $this->estimateService->calculateFeedingDays(
            (int) $data['fasting_day'],
            (int) $data['month'],
            (int) $data['year']
        );

        $daysInfo['fasting_day_name'] = $this->estimateService->getFastingDayName((int) $data['fasting_day']);
        $daysInfo['month_name'] = $this->estimateService->getMonthName((int) $data['month']);

        return $this->json($daysInfo);
    }
}