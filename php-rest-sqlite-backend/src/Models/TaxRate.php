<?php

namespace App\Models;

class TaxRate extends BaseModel
{
    protected static string $tableName = 'tax_rates';

    // Explicit property definitions to avoid PHP 8.2 deprecation warnings
    public ?int $id = null;
    public ?string $name = null;
    public ?float $rate = null;
    public ?string $region = null;
    public ?int $is_default = null;
    public ?string $description = null;
    public ?int $is_active = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public static function calculateTax(float $taxableAmount, ?string $region): array
    {
        $taxRate = null;
        if ($region) {
            $taxRate = self::fetchOne('SELECT * FROM tax_rates WHERE region = :region AND is_active = 1', [':region' => $region]);
        }

        if (!$taxRate) {
            $taxRate = self::fetchOne('SELECT * FROM tax_rates WHERE is_default = 1 AND is_active = 1');
        }

        if (!$taxRate) {
            return [
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_with_tax' => $taxableAmount,
            ];
        }

        $rate = $taxRate->rate / 100;
        $taxAmount = $taxableAmount * $rate;
        $totalWithTax = $taxableAmount + $taxAmount;
        return [
            'tax_rate' => $taxRate->rate,
            'tax_amount' => $taxAmount,
            'total_with_tax' => $totalWithTax,
        ];
    }
}
