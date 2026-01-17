<?php

namespace App\Service;

use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
use App\Repository\DietItemRepository;
use App\Repository\FeedItemRepository;

use App\Repository\AnimalCategoryRepository;
use Symfony\Component\HttpFoundation\Request;


class EstimateService
{
    private FeedItemRepository $feedItemRepository;
    private DietItemRepository $dietItemRepository;
    private AnimalSpeciesRepository $animalSpeciesRepository;

    private AnimalCategoryRepository $animalCategoryRepository;

    private AnimalPopulationRepository $animalPopulationRepository;

    public function __construct(
        FeedItemRepository $feedItemRepository,
        DietItemRepository $dietItemRepository,
        AnimalCategoryRepository $animalCategoryRepository,
        AnimalSpeciesRepository $animalSpeciesRepository,
        AnimalPopulationRepository $animalPopulationRepository

    ) {
        $this->feedItemRepository = $feedItemRepository;
        $this->dietItemRepository = $dietItemRepository;
        $this->animalCategoryRepository = $animalCategoryRepository;
        $this->animalSpeciesRepository=$animalSpeciesRepository;
        $this->animalPopulationRepository=$animalPopulationRepository;

    }

    /**
     * Calculate feeding days based on month, year and fasting day
     */
    public function calculateFeedingDays(int $fastingDay, int $month, int $year): array
    {
        if ($month === -1) {
            $totalDays = $feedingDays = 30;
        } else {
            $totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $feedingDays = ($fastingDay === -1)
                ? $totalDays
                : $totalDays - $this->countWeekdaysInMonth($fastingDay, $month, $year);
        }

        return [
            'total_days' => $totalDays,
            'feeding_days' => $feedingDays,
            'fasting_days' => $totalDays - $feedingDays,
        ];
    }

    /**
     * Count occurrences of a specific weekday in a month
     */
    public function countWeekdaysInMonth(int $weekday, int $month, int $year): int
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

    /**
     * Generate feed consumption estimates
     */
    public function generateFeedEstimates(int $fastingDay, int $month, int $year): array
    {
        // Calculate feeding days
        $daysInfo = $this->calculateFeedingDays($fastingDay, $month, $year);
        $feedingDays = $daysInfo['feeding_days'];

        // Get aggregated feed consumption per day
        $dailyFeedTotals = $this->dietItemRepository->getDailyFeedConsumption();

        $estimates = [];
        $totalPrice = 0;

        foreach ($dailyFeedTotals as $item) {
            $feedItemId = $item['feedItemId'];
            $feedItem = $this->feedItemRepository->find($feedItemId);

            if (!$feedItem) {
                continue;
            }

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

        return [
            'month' => $month,
            'year' => $year,
            'total_days' => $daysInfo['total_days'],
            'feeding_days' => $feedingDays,
            'fasting_days' => $daysInfo['fasting_days'],
            'estimates' => $estimates,
            'total_price' => round($totalPrice, 2),
            'currency' => 'INR',
        ];
    }


    public function getPerDayFeedEstimate(Request $request): array
    {
        $month = $request->query->getInt('month', (int) date('n'));
        $year = $request->query->getInt('year', (int) date('Y'));
        $fastingDay = $request->query->getInt('fastingDay', -1);
        $categoryId = $request->query->getInt('category');

        $category = $this->animalCategoryRepository->find($categoryId);
        if (!$category) {
            throw new \InvalidArgumentException('Invalid animal category selected.');
        }

        // Reuse service methods — one place to change logic
        $daysInfo = $this->calculateFeedingDays($fastingDay, $month, $year);
        $daysInfo['fasting_day_name'] = $this->getFastingDayName($fastingDay);
        $daysInfo['month_name'] = $this->getMonthName($month);

        $feedItems = $this->getFeedItemsByCategoryId($categoryId);
        $animalsData = $this->getAnimalsByCategoryId($categoryId);

        $columnTotals = [];
        foreach ($animalsData as $a) {
            $columnTotals[$a['id']] = 0.0;
        }

        $feedItemsData = [];
        foreach ($feedItems as $feedItem) {
            $unitName = $feedItem->getUnit()->getName();
            $diets = [];
            $dailyTotal = 0.0;

            foreach ($animalsData as $animal) {
                $animalId = $animal['id'];
                $dietPerAnimal = (float) $this->getDietAmountForFeedItemAndAnimal($feedItem, $animalId);
                $requiredPerDay = $dietPerAnimal * $animal['quantity'];
                $dailyTotal += $requiredPerDay;
                $columnTotals[$animalId] += $requiredPerDay;

                $diets[$animalId] = [
                    'per_animal' => $dietPerAnimal,
                    'required_for_quantity' => $requiredPerDay,
                ];
            }

            $feedItemsData[] = [
                'id' => $feedItem->getId(),
                'name' => $feedItem->getName(),
                'unit' => $unitName,
                'diets' => $diets,
                'daily_total' => $dailyTotal,
            ];
        }

        $grandTotal = array_reduce($feedItemsData, fn($carry, $fi) => $carry + $fi['daily_total'], 0.0);

        return [
            'month' => $month,
            'year' => $year,
            'fasting_day' => $fastingDay,
            'category' => $category,
            'days_info' => $daysInfo,
            'animals' => $animalsData,
            'feed_items' => $feedItemsData,
            'column_totals' => $columnTotals,
            'grand_total' => $grandTotal,
        ];
    }


    public function feedEstimateForAYear(int $year, int $fastingDay, int $categoryId): array
    {
        // validate category
        $category = $this->animalCategoryRepository->find($categoryId);
        if (!$category) {
            throw new \InvalidArgumentException('Invalid animal category selected.');
        }

        // Helper: count occurrences of a weekday (0..6) in a given month/year
        $countWeekdayOccurrences = function (int $year, int $month, int $weekday): int {
            if ($weekday < 0 || $weekday > 6) {
                return 0;
            }
            $daysInMonth = (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $count = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                if ((int) date('w', mktime(0, 0, 0, $month, $d, $year)) === $weekday) {
                    $count++;
                }
            }
            return $count;
        };

        $feedItems = $this->getFeedItemsByCategoryId($categoryId);
        $animalsData = $this->getAnimalsByCategoryId($categoryId);

        // Prepare month meta (days in month, fasting occurrences, effective days)
        $monthsInfo = [];
        $totalEffectiveDaysInYear = 0;
        for ($m = 1; $m <= 12; $m++) {
            $daysInMonth = (int) cal_days_in_month(CAL_GREGORIAN, $m, $year);
            $fastingOccurrences = 0;
            if ($fastingDay >= 0 && $fastingDay <= 6) {
                $fastingOccurrences = $countWeekdayOccurrences($year, $m, $fastingDay);
            }
            $effectiveDays = max(0, $daysInMonth - $fastingOccurrences);
            $monthsInfo[$m] = [
                'month' => $m,
                'month_name' => $this->getMonthName($m),
                'days_in_month' => $daysInMonth,
                'fasting_occurrences' => $fastingOccurrences,
                'effective_days' => $effectiveDays,
            ];
            $totalEffectiveDaysInYear += $effectiveDays;
        }

        // Initialize result containers
        $feedItemsTable = []; // rows: feedItem with monthly_totals[1..12], year_total, quantity_per_day, rate_per_kg, annual_cost
        $animalsMonthlyTotals = []; // animalId => [1..12, 'year_total']
        $monthlyGrandTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyGrandTotals[$m] = 0.0;
        }
        $yearlyGrandTotal = 0.0;
        $totalAnnualCost = 0.0;

        // Initialize animalsMonthlyTotals
        foreach ($animalsData as $animal) {
            $aid = $animal['id'];
            $animalsMonthlyTotals[$aid] = [];
            for ($m = 1; $m <= 12; $m++) {
                $animalsMonthlyTotals[$aid][$m] = 0.0;
            }
            $animalsMonthlyTotals[$aid]['year_total'] = 0.0;
        }

        // Cache diet lookups for performance (feedItemId => animalId => dietPerAnimal)
        $dietCache = [];

        // Build table rows
        foreach ($feedItems as $feedItem) {
            $monthlyTotals = [];
            $feedItemYearTotal = 0.0;
            $unitName = $feedItem->getUnit()->getName();

            // compute quantity_per_day (across all animals) once
            $quantityPerDay = 0.0;

            for ($month = 1; $month <= 12; $month++) {
                $effectiveDays = $monthsInfo[$month]['effective_days'];
                $monthlyTotalForFeedItem = 0.0;

                foreach ($animalsData as $animal) {
                    $animalId = $animal['id'];

                    // cache diet lookup
                    if (!isset($dietCache[$feedItem->getId()][$animalId])) {
                        $dietCache[$feedItem->getId()][$animalId] = (float) $this->getDietAmountForFeedItemAndAnimal($feedItem, $animalId);
                    }
                    $dietPerAnimal = $dietCache[$feedItem->getId()][$animalId];

                    $requiredPerDay = $dietPerAnimal * $animal['quantity'];          // per day across the quantity of this animal type

                    // accumulate quantityPerDay once (month==1)
                    if ($month === 1) {
                        $quantityPerDay += $requiredPerDay;
                    }

                    $requiredForMonth = $requiredPerDay * $effectiveDays;

                    // accumulate
                    $monthlyTotalForFeedItem += $requiredForMonth;
                    $animalsMonthlyTotals[$animalId][$month] += $requiredForMonth;
                    $animalsMonthlyTotals[$animalId]['year_total'] += $requiredForMonth;
                }

                $monthlyTotals[$month] = $monthlyTotalForFeedItem;
                $monthlyGrandTotals[$month] += $monthlyTotalForFeedItem;
                $feedItemYearTotal += $monthlyTotalForFeedItem;
            }

            // try to read a rate from the feedItem if available (safe check)
            $ratePerKg = 0.0;
            if (is_object($feedItem)) {
                $ratePerKg = (float) $feedItem->getEstimatedPrice();
            }

            $annualAmount = $feedItemYearTotal; // kg
            $annualCost = $ratePerKg > 0 ? ($annualAmount * $ratePerKg) : 0.0;

            $feedItemsTable[] = [
                'id' => $feedItem->getId(),
                'name' => $feedItem->getName(),
                'unit' => $unitName,
                'quantity_per_day' => $quantityPerDay,      // kg per day across all animals of this category
                'monthly_totals' => $monthlyTotals,         // keys 1..12
                'year_total' => $feedItemYearTotal,         // kg per year
                'rate_per_kg' => $ratePerKg,
                'annual_cost' => $annualCost,
            ];

            $yearlyGrandTotal += $feedItemYearTotal;
            $totalAnnualCost += $annualCost;
        }

        // Build monthly totals with final year_total (sum of Jan..Dec)
        $monthlyTotalsWithYear = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTotalsWithYear[$m] = $monthlyGrandTotals[$m] ?? 0.0;
        }
        $monthlyTotalsWithYear['year_total'] = array_reduce(
            array_slice($monthlyTotalsWithYear, 0, 12, true),
            function ($carry, $val) {
                return $carry + (float)$val;
            },
            0.0
        );

        return [
            'year' => $year,
            'fasting_day' => $fastingDay,
            'fasting_day_name' => $this->getFastingDayName($fastingDay),
            'category' => $category,
            'animals' => $animalsData,                      // added: animals list for template convenience
            'months_info' => $monthsInfo,                   // per-month meta including effective_days
            'total_effective_days_in_year' => $totalEffectiveDaysInYear,
            'feed_items_table' => $feedItemsTable,          // id,name,unit, quantity_per_day, monthly 1..12, year_total, rate_per_kg, annual_cost
            'monthly_grand_totals' => $monthlyTotalsWithYear,// 1..12 + 'year_total'
            'animals_monthly_totals' => $animalsMonthlyTotals,// animalId => [1..12, 'year_total']
            'yearly_grand_total' => $yearlyGrandTotal,
            'total_annual_cost' => $totalAnnualCost,       // sum of all annual_cost values
        ];
    }






    // In EstimateService.php
    public function getAllAnimalCategories(): array
    {
        return $this->animalCategoryRepository->findAll();
    }

    public function getAnimalsByCategoryId(int $categoryId): array
    {
        // Assuming you have an AnimalRepository
        $animals = $this->animalSpeciesRepository->findBy(['category' => $categoryId]);

        // Convert to simple array format
        $result = [];
        foreach ($animals as $animal) {

            $animalPopulation = $this->animalPopulationRepository->findLatestBySpecies($animal->getId());

            if ($animalPopulation==null) {
                $quantity=0;
            }else{

                $closingStock=$animalPopulation->getClosing();
                $quantity=$closingStock->getMale()+ $closingStock->getFemale();
            }

            $result[] = [
                'id' => $animal->getId(),
                'name' => $animal->getCommonName(),
                'quantity' => $quantity, // Assuming you have a quantity field
            ];
        }

        return $result;
    }

    public function getFeedItemsByCategoryId(int $categoryId): array
    {
        return $this->feedItemRepository->findByCategory($categoryId);
    }


    public function getDietAmountForFeedItemAndAnimal(object $feedItem, int $animalId): float
    {
        $dietItem = $this->dietItemRepository->findOneBy([
            'species'   => $animalId,
            'feedItem' => $feedItem->getId()
        ]);

        return $dietItem ? (float) $dietItem->getQuantity() : 0.0;
    }


    /**
     * Validate request data for feed estimation
     */
    public function validateFeedEstimationRequest(array $data): ?string
    {
        if (!isset($data['fasting_day'], $data['month'], $data['year'])) {
            return 'Missing required parameters: fasting_day, month, year';
        }

        // Additional validation can be added here
        if ($data['month'] !== -1 && ($data['month'] < 1 || $data['month'] > 12)) {
            return 'Invalid month value';
        }

        if ($data['year'] < 2000 || $data['year'] > 2100) {
            return 'Invalid year value';
        }

        return null; // No error
    }

    /**
     * Validate supply order request data
     */
    public function validateSupplyOrderRequest(array $data): ?string
    {
        $requiredParams = [
            'file_number', 'memo_number', 'date',
            'supplier_name', 'supplier_address', 'month',
            'year', 'terms_conditions', 'fasting_day'
        ];

        foreach ($requiredParams as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                return "Missing required parameter: $param";
            }
        }

        return null; // No error
    }

    /**
     * Get fasting day name from day number
     */
    public function getFastingDayName(int $fastingDay): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $fastingDay === -1 ? 'No fasting day' : $days[$fastingDay];
    }

    /**
     * Get month name from month number
     */
    public function getMonthName(int $month): string
    {
        if ($month === -1) {
            return 'Custom Period';
        }
        return date('M', mktime(0, 0, 0, $month, 10));
    }
}