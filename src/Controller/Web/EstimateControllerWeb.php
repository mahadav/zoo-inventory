<?php

namespace App\Controller\Web;

use App\Repository\AnimalCategoryRepository;
use App\Service\EstimateService;
use App\Service\PdfGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


//api endpoint /estimate/supply-order/form?month=3&year=2024&fastingDay=0
class EstimateControllerWeb extends AbstractController
{
    private EstimateService $estimateService;
    private PdfGeneratorService $pdfGeneratorService;

    private AnimalCategoryRepository $animalCategoryRepository;

    public function __construct(
        EstimateService $estimateService,
        PdfGeneratorService $pdfGeneratorService,
        AnimalCategoryRepository $animalCategoryRepository

    ) {
        $this->estimateService = $estimateService;
        $this->pdfGeneratorService = $pdfGeneratorService;
        $this->animalCategoryRepository=$animalCategoryRepository;
    }

    // inside your Controller

    #[Route('/estimate/feed-table', name: 'feed_estimate_table', methods: ['GET'])]
    public function showPerDayFeedEstimate(Request $request): Response
    {
        try {
            $viewData = $this->estimateService->getPerDayFeedEstimate($request);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('select_animal_category');
        }

        // render the page which includes the shared partial
        return $this->render('estimate/feed_estimate_table.html.twig', $viewData);
    }

    #[Route('/estimate/export/yearly/feed', name: 'export_yearly_feed', methods: ['GET'])]
    public function exportYearlyFeedEstimate(Request $request): Response
    {
        $year = $request->query->getInt('year', (int) date('Y'));
        $fastingDay = $request->query->getInt('fastingDay', -1);
        $categoryId = $request->query->getInt('category');
        try {
            $templateData = $this->estimateService->feedEstimateForAYear($year,$fastingDay,$categoryId);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('select_animal_category');
        }

        // optional: add a PDF-only flag so the twig can conditionally adjust layout/font sizes
        $templateData['pdf'] = true;


        // create a friendly filename
        $filename = sprintf(
            'feed-estimate-yearly-%s-%d.pdf',
            $templateData['category']->getId(),
            $templateData['year']
        );
        //dd($templateData);die;

        // call your PdfGeneratorService method (it internally renders the PDF twig)
        return $this->pdfGeneratorService->generateYearlyFeedEstimate($templateData, $filename, 'estimate/yearly/pdf_table.html.twig');
    }


    #[Route('/estimate/feed-table/export/pdf', name: 'feed_estimate_export_pdf', methods: ['GET'])]
    public function exportFeedEstimatePdf(Request $request): Response
    {
        try {
            // buildFeedEstimateViewData() should return the exact array keys your PDF twig expects:
            // 'month','year','category','days_info','animals','feed_items','column_totals','grand_total', etc.
            $templateData = $this->estimateService->getPerDayFeedEstimate($request);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('select_animal_category');
        }

        // optional: add a PDF-only flag so the twig can conditionally adjust layout/font sizes
        $templateData['pdf'] = true;

        // create a friendly filename
        $filename = sprintf(
            'feed-estimate-%s-%02d-%d.pdf',
            $templateData['category']->getId(),
            $templateData['month'],
            $templateData['year']
        );

        // call your PdfGeneratorService method (it internally renders the PDF twig)
        return $this->pdfGeneratorService->generateFeedEstimateTablePdf($templateData, $filename, 'estimate/pdf_feed_estimate_table.html.twig');
    }

    /**
     * Render a year-wise feed estimate table (HTML view).
     *
     * Example URL:
     * /estimate/yearly/view?year=2025&fastingDay=1&category=2
     */

    #[Route('/estimate/yearly/view', name: 'web_estimate_yearly_view', methods: ['GET'])]
    public function yearlyView(Request $request): Response
    {
        $year = $request->query->getInt('year', (int) date('Y'));
        $fastingDay = $request->query->getInt('fastingDay', -1);
        $categoryId = $request->query->getInt('category', 0);

        $category = null;
        if ($categoryId > 0) {
            $category = $this->animalCategoryRepository->find($categoryId);
        }

        // If category not provided or invalid, pick first available
        if (!$category) {
            $first = $this->animalCategoryRepository->findOneBy([]);
            if ($first) {
                $categoryId = $first->getId();
                $category = $first;
            } else {
                // No categories exist — let the service handle invalid category if necessary
                $categoryId = 0;
            }
        }

        // Get the main payload from service
        $data = $this->estimateService->feedEstimateForAYear($year, $fastingDay, $categoryId);

        // Ensure template has animals list (id, name, quantity)
        // The service returns animals_monthly_totals but not necessarily the animals themselves,
        // so fetch animals and attach to the data array to keep template simple.
        $animals = $this->estimateService->getAnimalsByCategoryId($categoryId);
        $data['animals'] = $animals;

        // pass categories for selector (so user can choose category)
        $categories = $this->animalCategoryRepository->findAll();

        return $this->render('estimate/yearly/table.html.twig', [
            'data' => $data,
            'categories' => $categories,
        ]);
    }

    #[Route('/estimate/select-category', name: 'select_animal_category', methods: ['GET'])]
    public function selectAnimalCategory(Request $request): Response
    {
        $year = $request->query->getInt('year', date('Y'));
        $fastingDay = $request->query->getInt('fastingDay', -1);

        // Get categories from database
        $categories = $this->estimateService->getAllAnimalCategories();

        return $this->render('estimate/animal_selection_form.html.twig', [
            'year' => $year,
            'fastingDay' => $fastingDay,
            'categories' => $categories,
        ]);
    }


    /**
     * Display the supply order form
     */
    #[Route('/estimate/supply-order/form', name: 'supply_order_form', methods: ['GET'])]
    public function showSupplyOrderForm(Request $request): Response
    {
        // Get parameters from query string or session
        $month = $request->query->getInt('month', date('n'));
        $year = $request->query->getInt('year', date('Y'));
        $fastingDay = $request->query->getInt('fastingDay', -1);

        // Get feeding days information
        $daysInfo = $this->estimateService->calculateFeedingDays($fastingDay, $month, $year);
        $daysInfo['fasting_day_name'] = $this->estimateService->getFastingDayName($fastingDay);
        $daysInfo['month_name'] = $this->estimateService->getMonthName($month);

        // Get default terms and conditions
        $defaultTerms = $this->getDefaultTermsAndConditions();
        $defaultPaymentTerms = $this->getDefaultPaymentTerms();

        // Get current date + 7 days for delivery date
        $deliveryDate = new \DateTime();
        $deliveryDate->modify('+7 days');

        // Generate feed estimates for preview
        $estimates = $this->estimateService->generateFeedEstimates($fastingDay, $month, $year);

        return $this->render('estimate/supply_order_form.html.twig', [
            'month' => $month,
            'year' => $year,
            'fasting_day' => $fastingDay,
            'days_info' => $daysInfo,
            'default_terms' => $defaultTerms,
            'default_payment_terms' => $defaultPaymentTerms,
            'delivery_date' => $deliveryDate->format('Y-m-d'),
            'estimates' => $estimates,
        ]);
    }

    /**
     * Process supply order form submission
     */
    #[Route('/estimate/supply-order/submit', name: 'supply_order_submit', methods: ['POST'])]
    public function submitSupplyOrder(Request $request): Response
    {
        $data = $request->request->all();

        // Validate required parameters
        $validationError = $this->estimateService->validateSupplyOrderRequest($data);
        if ($validationError) {
            $this->addFlash('error', $validationError);
            return $this->redirectToRoute('supply_order_form', [
                'month' => $data['month'] ?? date('n'),
                'year' => $data['year'] ?? date('Y'),
                'fastingDay' => $data['fasting_day'] ?? -1,
            ]);
        }

        try {
            // Generate feed estimates
            $estimateData = $this->estimateService->generateFeedEstimates(
                (int) $data['fasting_day'],
                (int) $data['month'],
                (int) $data['year']
            );

            // Get logo path
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/logo.png';

            // Add feeding days to data for PDF
            $data['feeding_days'] = $estimateData['feeding_days'];

            // Generate PDF
            $response = $this->pdfGeneratorService->generateSupplyOrderPdf(
                $data,
                $logoPath,
                $estimateData['estimates']
            );

            // You could also save the PDF to database/filesystem here
            $this->addFlash('success', 'Supply order generated successfully!');

            return $response;

        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to generate PDF: ' . $e->getMessage());
            return $this->redirectToRoute('supply_order_form', [
                'month' => $data['month'],
                'year' => $data['year'],
                'fastingDay' => $data['fasting_day'],
            ]);
        }
    }

    /**
     * AJAX endpoint to validate form inputs
     */
    #[Route('/estimate/supply-order/validate', name: 'supply_order_validate', methods: ['POST'])]
    public function validateSupplyOrderAjax(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $errors = [];

        // Validate individual fields
        if (empty($data['supplier_name'] ?? '')) {
            $errors['supplier_name'] = 'Supplier name is required';
        }

        if (empty($data['supplier_address'] ?? '')) {
            $errors['supplier_address'] = 'Supplier address is required';
        }

        if (empty($data['supplier_contact'] ?? '')) {
            $errors['supplier_contact'] = 'Supplier contact is required';
        } elseif (!preg_match('/^[+]?[0-9]{10,15}$/', $data['supplier_contact'])) {
            $errors['supplier_contact'] = 'Invalid contact number';
        }

        if (empty($data['file_number'] ?? '')) {
            $errors['file_number'] = 'File number is required';
        }

        if (empty($data['memo_number'] ?? '')) {
            $errors['memo_number'] = 'Memo number is required';
        }

        if (empty($data['date'] ?? '')) {
            $errors['date'] = 'Delivery date is required';
        }

        if (empty($data['payment_terms'] ?? '')) {
            $errors['payment_terms'] = 'Payment terms are required';
        }

        if (empty($data['terms_conditions'] ?? '')) {
            $errors['terms_conditions'] = 'Terms & conditions are required';
        }

        if (count($errors) > 0) {
            return $this->json([
                'valid' => false,
                'errors' => $errors
            ], 400);
        }

        return $this->json([
            'valid' => true,
            'message' => 'All inputs are valid'
        ]);
    }

    /**
     * Preview supply order (HTML version)
     */
    #[Route('/estimate/supply-order/preview', name: 'supply_order_preview', methods: ['POST'])]
    public function previewSupplyOrder(Request $request): Response
    {
        $data = $request->request->all();

        // Generate feed estimates
        $estimateData = $this->estimateService->generateFeedEstimates(
            (int) $data['fasting_day'],
            (int) $data['month'],
            (int) $data['year']
        );

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/logo.png';

        return $this->render('estimate/supply_order_preview.html.twig', [
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
            'feeding_days' => $estimateData['feeding_days'],
            'logo_path' => $logoPath,
            'supplier_contact' => $data['supplier_contact'] ?? '',
            'payment_terms' => $data['payment_terms'] ?? '',
        ]);
    }

    /**
     * Get default terms and conditions
     */
    private function getDefaultTermsAndConditions(): string
    {
        return "1. All goods must be delivered in perfect condition.\n" .
            "2. Payment will be made within 30 days of delivery.\n" .
            "3. Any damaged goods will be returned at supplier's expense.\n" .
            "4. All deliveries must be made during business hours (9 AM - 5 PM).\n" .
            "5. The supplier is responsible for proper packaging and handling.";
    }

    /**
     * Get default payment terms
     */
    private function getDefaultPaymentTerms(): string
    {
        return "Net 30 days from date of delivery";
    }
}