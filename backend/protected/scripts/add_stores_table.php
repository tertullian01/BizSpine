<?php
declare(strict_types=1);

// Add stores table to the database
// Run from project root:
// php backend/protected/scripts/add_stores_table.php

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

    // Create stores table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS stores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT,
  address TEXT,
  phone TEXT,
  email TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL
    );

    echo "Stores table created successfully.\n";

    // Insert default stores: Siedlung and USA
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO stores (name, description) VALUES (:name, :description)');
    
    $stores = [
        ['name' => 'Siedlung', 'description' => 'Siedlung store location'],
        ['name' => 'USA', 'description' => 'USA store location']
    ];

    foreach ($stores as $store) {
        $stmt->execute($store);
    }

    echo "Default stores (Siedlung, USA) inserted.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show stores
    $stmt = $pdo->query("SELECT * FROM stores;");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nStores in database:\n";
    foreach ($stores as $store) {
        echo " - ID: {$store['id']}, Name: {$store['name']}, Description: {$store['description']}\n";
    }

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding stores table: " . $e->getMessage() . "\n");
    exit(1);
}