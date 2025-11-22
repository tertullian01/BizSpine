<?php

namespace App\Services;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Return a PDO instance for the given SQLite path.
     * Expects full path like .../protected/db/database.sqlite
     */
    public static function get(string $dbPath): PDO
    {
        if (self::$pdo === null) {
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
                @chmod($dir, 0700);
            }

            $dsn = "sqlite:" . $dbPath;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }
}
