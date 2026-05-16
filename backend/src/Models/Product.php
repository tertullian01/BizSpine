<?php

namespace App\Models;

class Product extends BaseModel
{
    protected static string $tableName = 'products';

    public ?int $id = null;
    public ?string $name = null;
    public ?string $type = null;
    public ?string $description = null;
    public ?string $featured_ingredients = null;
    public ?string $all_ingredients = null;
    public ?string $size = null;
    public ?float $cost = null;
    public ?string $image_url = null;
    public ?string $state = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
