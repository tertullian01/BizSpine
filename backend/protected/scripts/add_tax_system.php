<?php
declare(strict_types=1);

// Add tax management system
// Run from project root:
// php backend/protected/scripts/add_tax_system.php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config not found at: $configPath\n");
    exit(1);
}

$config = require $configPath;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "db_path not set in config\n");
    exit(1);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Create tax_rates table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS tax_rates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  rate REAL NOT NULL,
  region TEXT,
  is_default INTEGER DEFAULT 0,
  is_active INTEGER DEFAULT 1,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL
    );

    echo "Tax rates table created successfully.\n";

    // Add tax fields to orders table
    $pdo->exec('ALTER TABLE orders ADD COLUMN tax_rate REAL DEFAULT 0');
    $pdo->exec('ALTER TABLE orders ADD COLUMN tax_amount REAL DEFAULT 0');

    echo "Tax fields added to orders table.\n";

    // Create indexes
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tax_rates_active ON tax_rates(is_active);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tax_rates_region ON tax_rates(region);');

    echo "Indexes created successfully.\n";

    // Insert default tax rates
    $pdo->exec("INSERT INTO tax_rates (name, rate, region, is_default, description) VALUES ('Germany VAT', 19.0, 'DE', 1, 'Standard German VAT rate')");
    $pdo->exec("INSERT INTO tax_rates (name, rate, region, is_default, description) VALUES ('USA Sales Tax', 7.5, 'US', 0, 'Average US sales tax')");

    echo "Default tax rates inserted.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show tax_rates table structure
    $stmt = $pdo->query("PRAGMA table_info(tax_rates);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTax rates table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show updated orders table structure
    $stmt = $pdo->query("PRAGMA table_info(orders);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nOrders table (showing tax fields):\n";
    foreach ($columns as $col) {
        if (strpos($col['name'], 'tax') !== false) {
            echo " - {$col['name']} ({$col['type']})\n";
        }
    }

    // Show tax rates
    $stmt = $pdo->query("SELECT * FROM tax_rates;");
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nConfigured tax rates:\n";
    foreach ($rates as $rate) {
        echo " - {$rate['name']}: {$rate['rate']}% ({$rate['region']})" . ($rate['is_default'] ? ' [DEFAULT]' : '') . "\n";
    }

    echo "\nTax management system ready.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding tax system: " . $e->getMessage() . "\n");
    exit(1);
}