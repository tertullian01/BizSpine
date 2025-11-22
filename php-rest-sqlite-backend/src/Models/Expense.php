<?php
namespace App\Models;

class Expense extends BaseModel
{
    protected static string $tableName = 'expenses';
    
    // Additional properties for joined data
    public ?string $order_number;

    public static function getTotalExpenses(): float
    {
        $stmt = self::$db->query('SELECT COALESCE(SUM(amount), 0) as total FROM expenses');
        return (float)$stmt->fetchColumn();
    }

    public static function getExpensesByCategory(): array
    {
        $categoryStmt = self::$db->query('SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC');
        return $categoryStmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}