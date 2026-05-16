<?php

namespace App\Models;

class Category extends BaseModel
{
    protected static string $tableName = 'categories';

    public ?string $name = null;
    public ?string $type = null; // 'income' or 'expense'
    public ?string $color = null;
    public ?string $icon = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}