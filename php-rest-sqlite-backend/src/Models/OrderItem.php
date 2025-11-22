<?php

namespace App\Models;

class OrderItem extends BaseModel
{
    protected static string $tableName = 'order_items';
// Additional properties for joined data
    public ?string $product_name;
    public ?string $store_name;
}
