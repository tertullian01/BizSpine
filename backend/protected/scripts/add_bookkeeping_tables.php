<?php
declare(strict_types=1);

// Add income and expenses tables for bookkeeping
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/add_bookkeeping_tables.php

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

    // Create income table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS income (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER,
  amount REAL NOT NULL,
  payment_method TEXT,
  payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  description TEXT,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
);
SQL
    );

    echo "Income table created successfully.\n";

    // Create expenses table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER,
  vendor TEXT,
  category TEXT NOT NULL,
  amount REAL NOT NULL,
  expense_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  description TEXT,
  receipt_image_url TEXT,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
);
SQL
    );

    echo "Expenses table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_income_order ON income(order_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_income_date ON income(payment_date);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_order ON expenses(order_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses(expense_date);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_vendor ON expenses(vendor);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show income table structure
    $stmt = $pdo->query("PRAGMA table_info(income);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nIncome table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show expenses table structure
    $stmt = $pdo->query("PRAGMA table_info(expenses);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nExpenses table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nBookkeeping system ready. Income and expenses can now be tracked.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding bookkeeping tables: " . $e->getMessage() . "\n");
    exit(1);
}