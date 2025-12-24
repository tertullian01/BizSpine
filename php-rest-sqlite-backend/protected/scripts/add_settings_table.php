<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Database;

// Load configuration
$config = require __DIR__ . '/../config/config.php';
$dbPath = $config['database']['database_path'];

echo "Target Database: " . $dbPath . "\n";

try {
    // Get database instance
    $db = Database::get($dbPath);
    
    // 1. Ensure settings table exists
    echo "Checking settings table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT NOT NULL UNIQUE,
        value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Add new columns
    $columnsToAdd = [
        'type' => "TEXT DEFAULT 'string'",
        'group_name' => "TEXT DEFAULT 'general'",
        'description' => "TEXT",
        'is_public' => "INTEGER DEFAULT 0"
    ];

    $stmt = $db->query("PRAGMA table_info(settings)");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

    foreach ($columnsToAdd as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            echo "Adding column '$column' to settings table...\n";
            $db->exec("ALTER TABLE settings ADD COLUMN $column $definition");
        }
    }

    echo "Settings table migration completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}