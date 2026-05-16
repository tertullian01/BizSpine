<?php

namespace Tests;

use PDO;
use App\Services\Config as AppConfig;
use App\Services\Database;
use App\Models\BaseModel;

class DatabaseTestCase extends TestCase
{
    protected static ?PDO $db = null;
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$db === null) {
            $backendRoot = dirname(__DIR__);
            $dbPath = $backendRoot . '/protected/database/testing.db';

            AppConfig::getInstance()->set('database.database_path', $dbPath);

            if (!self::$migrated) {
                self::runTestingMigrations($backendRoot, $dbPath);
                self::$migrated = true;
            }

            self::$db = new PDO('sqlite:' . $dbPath);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db->exec('PRAGMA foreign_keys = ON;');

            self::ensureSqliteTestSchema(self::$db);

            BaseModel::setDatabase(self::$db);
            Database::setInstance(self::$db);
        }

        self::truncateAllTables(self::$db);
    }

    private static function runTestingMigrations(string $backendRoot, string $dbPath): void
    {
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        $phinx = $backendRoot . '/vendor/bin/phinx';
        $config = $backendRoot . '/phinx.php';
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($phinx)
            . ' migrate -c ' . escapeshellarg($config)
            . ' -e testing 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'Phinx migrate failed (exit ' . $exitCode . "):\n" . implode("\n", $output)
            );
        }

        if (!is_file($dbPath)) {
            throw new \RuntimeException('Testing database was not created at: ' . $dbPath);
        }
    }

    private static function truncateAllTables(PDO $db): void
    {
        $tables = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'phinxlog'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $db->exec('PRAGMA foreign_keys = OFF;');
        foreach ($tables as $table) {
            $escaped = str_replace('"', '""', $table);
            $db->exec('DELETE FROM "' . $escaped . '";');
        }
        $db->exec('DELETE FROM sqlite_sequence;');
        $db->exec('PRAGMA foreign_keys = ON;');
    }

    /**
     * Columns added by legacy scripts but not yet in every Phinx migration path.
     */
    private static function ensureSqliteTestSchema(PDO $db): void
    {
        require_once dirname(__DIR__) . '/tools/install_lib.php';
        bizspine_ensure_optional_columns($db);
        self::addSqliteColumnIfMissing($db, 'income', 'transaction_id', 'TEXT');
        self::addSqliteColumnIfMissing($db, 'inventory', 'price_override', 'REAL');
    }

    private static function addSqliteColumnIfMissing(PDO $db, string $table, string $column, string $sqlType): void
    {
        $escapedTable = str_replace('"', '""', $table);
        $rows = $db->query('PRAGMA table_info("' . $escapedTable . '")');
        if ($rows === false) {
            return;
        }
        $cols = $rows->fetchAll(PDO::FETCH_ASSOC);
        if ($cols === []) {
            return;
        }
        $names = array_column($cols, 'name');
        if (in_array($column, $names, true)) {
            return;
        }
        $colEsc = str_replace('"', '""', $column);
        $db->exec('ALTER TABLE "' . $escapedTable . '" ADD COLUMN "' . $colEsc . '" ' . $sqlType);
    }
}
