<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

class PdfGeneratorService
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Generate PDF from HTML template
     */
    public function generatePdfFromTemplate(
        string $template,
        array  $data,
        string $filename,
        string $orientation = 'portrait'
    ): Response
    {
        // Render HTML from template
        $html = $this->twig->render($template, $data);

        // Configure Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        // Return PDF as response
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * Generate supply order PDF
     */
    public function generateSupplyOrderPdf(array $data, string $logoPath, array $estimates): Response
    {
        $templateData = [
            'file_number' => $data['file_number'],
            'memo_number' => $data['memo_number'],
            'date' => $data['date'],
            'supplier_name' => $data['supplier_name'],
            'supplier_address' => $data['supplier_address'],
            'month' => $data['month'],
            'year' => $data['year'],
            'month_name' => date('F', mktime(0, 0, 0, $data['month'], 10)),
            'terms_conditions' => $data['terms_conditions'],
            'estimates' => $estimates,
            'feeding_days' => $data['feeding_days'] ?? 0,
            'logo_path' => $logoPath
        ];

        return $this->generatePdfFromTemplate(
            'supply_order.html.twig',
            $templateData,
            'supply_order.pdf'
        );
    }

    /**
     * Generate pricing estimate PDF
     */
    public function generatePricingEstimatePdf(array $data, array $estimateData): Response
    {
        $templateData = [
            'date_generated' => date('d/m/Y'),
            'month_name' => date('F', mktime(0, 0, 0, $data['month'], 10)),
            'year' => $data['year'],
            'estimates' => $estimateData['estimates'],
            'total_price' => $estimateData['total_price'],
            'currency' => $estimateData['currency'],
            'feeding_days' => $estimateData['feeding_days'],
            'total_days' => $estimateData['total_days'],
            'fasting_days' => $estimateData['fasting_days'],
            'fasting_day_name' => $data['fasting_day_name'] ?? 'No fasting day',
        ];

        return $this->generatePdfFromTemplate(
            'pricing_estimate.html.twig',
            $templateData,
            'pricing_estimate.pdf'
        );
    }

    public function generateFeedEstimateTablePdf(array $templateData, string $filename,string $twigFileName): Response
    {
        $html = $this->twig->render('estimate/pdf_feed_estimate_table.html.twig', $templateData);
        $dompdfString = $this->getPrintableString($html, 'landscape');
        return $this->getPdfResponse($dompdfString,$filename);

    }

    public function generateYearlyFeedEstimate(array $templateData, string $filename,string $twigFileName): Response
    {
        //dd($templateData);
        $html = $this->twig->render($twigFileName, ['data' => $templateData]);
        $dompdfString = $this->getPrintableString($html, 'landscape');
        return $this->getPdfResponse($dompdfString,$filename);
    }


    private function getPrintableString($html, $orientation): String
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A3', $orientation);
        //return $dompdf;
        $dompdf->render();

        return $dompdf->output();
    }

    private function getPdfResponse(string $dompdfString,string $fileName):Response{
        $response = new Response($dompdfString, Response::HTTP_OK);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }
}