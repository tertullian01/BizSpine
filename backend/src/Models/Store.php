<?php

namespace App\Models;

class Store extends BaseModel
{
    protected static string $tableName = 'stores';

    public ?string $name = null;
    public ?string $description = null;
    public ?string $location = null;
    public ?string $address = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $currency_symbol = '$';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function findByName(string $name): ?static
    {
        return self::fetchOne('SELECT * FROM ' . self::$tableName . ' WHERE name = :name', [':name' => $name]);
    }
}
