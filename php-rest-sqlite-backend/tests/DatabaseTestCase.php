<?php

namespace Tests;

use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
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
            // Set the database path for the testing environment
            AppConfig::getInstance()->set('database.database_path', __DIR__ . '/../protected/database/testing.db');

            $dbDir = __DIR__ . '/../protected/database';
            $dbPath = $dbDir . '/testing.db';

            // Ensure the database directory exists
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            // Only migrate if it hasn't been done in this process
            if (!self::$migrated) {
                // Ensure the database is clean before the first test
                if (file_exists($dbPath)) {
                    unlink($dbPath);
                }

                // Load the Phinx config array
                $configArray = require(__DIR__ . '/../phinx.php');

                // Use the Phinx Manager to run migrations programmatically
                $config = new Config($configArray);
                $manager = new Manager($config, new StringInput(''), new NullOutput());

                // Migrate the database for the 'testing' environment
                $manager->migrate('testing');
                self::$migrated = true;
            }

            self::$db = new PDO('sqlite:' . $dbPath);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db->exec('PRAGMA foreign_keys = ON;');

            // Set the database connection for models
            BaseModel::setDatabase(self::$db);

            // Set the database instance for Database service (used by controllers)
            Database::setInstance(self::$db);
        }

        // Truncate all tables to ensure a clean state for each test
        $tables = self::$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'phinxlog';")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            self::$db->exec("DELETE FROM `$table`;");
        }
    }
}
