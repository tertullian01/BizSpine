<?php

namespace App\Models;

class Income extends BaseModel
{
    protected static string $tableName = 'income';

    public ?int $order_id = null;
    public ?float $amount = null;
    public ?string $payment_method = null;
    public ?string $transaction_id = null;
    public ?string $category = null;
    public ?string $payment_date = null;
    public ?string $description = null;
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

// Additional properties for joined data
    public ?string $order_number;
    public static function getTotalIncome(): float
    {
        $stmt = self::$db->query('SELECT COALESCE(SUM(amount), 0) as total FROM income');
        return (float)$stmt->fetchColumn();
    }
}
