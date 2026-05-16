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
    public static function get(?string $dbPath = null): PDO
    {
        if (self::$pdo === null) {
            if ($dbPath === null) {
                throw new \RuntimeException('Database path required for first connection');
            }
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

    /**
     * Set the PDO instance directly (useful for testing)
     */
    public static function setInstance(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Reset the PDO instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
