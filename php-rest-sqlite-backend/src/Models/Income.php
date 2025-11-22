<?php
namespace App\Models;

class Income extends BaseModel
{
    protected static string $tableName = 'income';
    
    // Additional properties for joined data
    public ?string $order_number;

    public static function getTotalIncome(): float
    {
        $stmt = self::$db->query('SELECT COALESCE(SUM(amount), 0) as total FROM income');
        return (float)$stmt->fetchColumn();
    }
}