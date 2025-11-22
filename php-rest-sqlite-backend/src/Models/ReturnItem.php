<?php

namespace App\Models;

class ReturnItem extends BaseModel
{
    protected static string $tableName = 'return_items';
// Additional properties for joined data
    public ?string $product_name;
    public ?string $store_name;
}
