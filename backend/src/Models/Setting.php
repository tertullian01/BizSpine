<?php

namespace App\Models;

class Setting extends BaseModel
{
    protected static string $tableName = 'settings';

    public ?string $key = null;
    public ?string $value = null;
    public ?string $group_name = null;
    public ?string $type = null;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}