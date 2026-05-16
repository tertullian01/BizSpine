<?php

/**
 * Migration Script for Bookkeeping Categories
 * Usage: php protected/scripts/migrate_categories.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Database;

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Error: Config file not found at $configFile\n");
}
$config = require $configFile;

$dbPath = $config['database']['database_path'] ?? __DIR__ . '/../db/database.sqlite';

echo "Starting Bookkeeping Categories Migration...\n";
echo "Target Database: $dbPath\n";

try {
    // Ensure directory exists
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $db = Database::get($dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create categories table
    echo "1. Checking 'categories' table...\n";
    $db->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL,
        color TEXT,
        icon TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    SQL);
    echo "   Done.\n";

    // 2. Add 'category' column to 'income' table
    echo "2. Checking 'income' table schema...\n";
    $stmt = $db->query("PRAGMA table_info(income)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('category', $columns)) {
        echo "   Adding 'category' column...\n";
        $db->exec("ALTER TABLE income ADD COLUMN category TEXT");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_income_category ON income(category)");
        echo "   Done.\n";
    } else {
        echo "   Column 'category' already exists.\n";
    }

    // 3. Seed default categories
    echo "3. Seeding default categories...\n";
    $defaults = [
        ['name' => 'Sales', 'type' => 'income', 'color' => '#28a745', 'icon' => '💰'],
        ['name' => 'Services', 'type' => 'income', 'color' => '#17a2b8', 'icon' => '🛠️'],
        ['name' => 'Rent', 'type' => 'expense', 'color' => '#dc3545', 'icon' => '🏠'],
        ['name' => 'Utilities', 'type' => 'expense', 'color' => '#ffc107', 'icon' => '💡'],
        ['name' => 'Supplies', 'type' => 'expense', 'color' => '#6c757d', 'icon' => '📦'],
        ['name' => 'Shipping', 'type' => 'expense', 'color' => '#007bff', 'icon' => '🚚'],
        ['name' => 'Marketing', 'type' => 'expense', 'color' => '#e83e8c', 'icon' => '📢'],
        ['name' => 'Refund', 'type' => 'expense', 'color' => '#fd7e14', 'icon' => '↩️'],
        ['name' => 'Inventory', 'type' => 'expense', 'color' => '#17a2b8', 'icon' => '📦'],
        ['name' => 'Software', 'type' => 'expense', 'color' => '#6610f2', 'icon' => '💻'],
    ];

    $checkStmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND type = :type");
    $insertStmt = $db->prepare("INSERT INTO categories (name, type, color, icon) VALUES (:name, :type, :color, :icon)");

    $addedCount = 0;
    foreach ($defaults as $cat) {
        $checkStmt->execute([':name' => $cat['name'], ':type' => $cat['type']]);
        if ($checkStmt->fetchColumn() == 0) {
            $insertStmt->execute([
                ':name' => $cat['name'],
                ':type' => $cat['type'],
                ':color' => $cat['color'],
                ':icon' => $cat['icon']
            ]);
            $addedCount++;
        }
    }
    echo "   Added $addedCount new categories.\n";

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}