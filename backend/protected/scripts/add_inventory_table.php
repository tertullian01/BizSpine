<?php
declare(strict_types=1);

// Add inventory table to track product quantities per store
// Run from project root:
// php backend/protected/scripts/add_inventory_table.php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config not found at: $configPath\n");
    exit(1);
}

$config = require $configPath;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "db_path not set in config (expected 'db_path' or 'database.database_path')\n");
    exit(1);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Create inventory table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventory (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  store_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 0,
  min_quantity INTEGER DEFAULT 0,
  max_quantity INTEGER DEFAULT NULL,
  price_override REAL DEFAULT NULL,
  last_restocked DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(product_id, store_id),
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
);
SQL
    );

    echo "Inventory table created successfully.\n";

    // Create index for faster lookups
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory(product_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inventory_store ON inventory(store_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inventory_product_store ON inventory(product_id, store_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inventory_low_stock ON inventory(quantity, min_quantity);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show inventory table structure
    $stmt = $pdo->query("PRAGMA table_info(inventory);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nInventory table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Check if we have products and stores to create sample inventory
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $storeCount = $pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn();

    if ($productCount > 0 && $storeCount > 0) {
        echo "\nFound $productCount products and $storeCount stores.\n";
        echo "You can now add inventory records using the API endpoints.\n";
    } else {
        echo "\nNote: Add products and stores before creating inventory records.\n";
    }

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding inventory table: " . $e->getMessage() . "\n");
    exit(1);
}