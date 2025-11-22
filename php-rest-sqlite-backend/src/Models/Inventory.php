<?php
namespace App\Models;

use PDO;

class Inventory extends BaseModel
{
    // Properties from JOINs
    public ?string $product_name = null;
    public ?string $store_name = null;

    protected static string $tableName = 'inventory';

    private static function getSelectQuery(): string
    {
        return <<<'SQL'
SELECT 
    i.*,
    p.name as product_name,
    s.name as store_name
FROM inventory i
LEFT JOIN products p ON i.product_id = p.id
LEFT JOIN stores s ON i.store_id = s.id
SQL;
    }

    public static function find(int $id): ?static
    {
        $sql = self::getSelectQuery() . ' WHERE i.id = :id';
        return self::fetchOne($sql, [':id' => $id]);
    }

    public static function findAll(): array
    {
        $sql = self::getSelectQuery() . ' ORDER BY s.name, p.name';
        return self::fetchAll($sql);
    }

    public static function findByProduct(int $productId): array
    {
        $sql = self::getSelectQuery() . ' WHERE i.product_id = :product_id ORDER BY s.name';
        return self::fetchAll($sql, [':product_id' => $productId]);
    }

    public static function findByStore(int $storeId): array
    {
        $sql = self::getSelectQuery() . ' WHERE i.store_id = :store_id ORDER BY p.name';
        return self::fetchAll($sql, [':store_id' => $storeId]);
    }

    public static function findLowStock(): array
    {
        $sql = self::getSelectQuery() . ' WHERE i.quantity <= i.min_quantity ORDER BY i.quantity ASC, s.name, p.name';
        return self::fetchAll($sql);
    }

    public function adjustQuantity(int $adjustment): void
    {
        $this->quantity += $adjustment;
        $this->last_restocked = date('Y-m-d H:i:s');
        $this->save();
    }
}