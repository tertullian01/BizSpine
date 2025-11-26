<?php

namespace App\Models;

class OrderItem extends BaseModel
{
    protected static string $tableName = 'order_items';

    public ?int $order_id;
    public ?int $product_id;
    public ?int $store_id;
    public ?int $quantity;
    public ?float $unit_price;
    public ?float $subtotal;
    public ?string $created_at;

    // Additional properties for joined data
    public ?string $product_name;
    public ?string $store_name;
}
