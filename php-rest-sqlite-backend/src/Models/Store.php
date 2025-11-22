<?php

namespace App\Models;

class Store extends BaseModel
{
    protected static string $tableName = 'stores';
    public static function findByName(string $name): ?static
    {
        return self::fetchOne('SELECT * FROM ' . self::$tableName . ' WHERE name = :name', [':name' => $name]);
    }
}
