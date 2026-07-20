<?php

namespace App\Services;

use App\Models\Setting;

class GradePricingService
{
    private array $b2bRules;
    private array $retailRules;

    public function __construct()
    {
        // B2B multipliers (wholesale)
        $this->b2bRules = Setting::getJson('grade_price_rules', [
            'S1' => 0.85,
            'S2' => 0.75,
            'S3' => 0.65,
            'S4' => 0.50,
            'S5' => 0.30,
        ]);

        // Retail multipliers (higher margin than B2B)
        $this->retailRules = Setting::getJson('grade_retail_price_rules', [
            'S1' => 0.95,
            'S2' => 0.85,
            'S3' => 0.75,
            'S4' => 0.60,
            'S5' => 0.40,
        ]);
    }

    public function calculateSellingPrice(float $purchasePrice, string $grade): float
    {
        $multiplier = $this->b2bRules[$grade] ?? 0.50;
        return round($purchasePrice * $multiplier, 2);
    }

    public function calculateRetailPrice(float $purchasePrice, string $grade): float
    {
        $multiplier = $this->retailRules[$grade] ?? 0.60;
        return round($purchasePrice * $multiplier, 2);
    }

    public function calculateBothPrices(float $purchasePrice, string $grade): array
    {
        return [
            'selling_price' => $this->calculateSellingPrice($purchasePrice, $grade),
            'retail_price'  => $this->calculateRetailPrice($purchasePrice, $grade),
        ];
    }

    public function getRules(): array
    {
        return [
            'b2b'    => $this->b2bRules,
            'retail' => $this->retailRules,
        ];
    }
}
