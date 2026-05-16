<?php
declare(strict_types=1);

// Add returns and refunds system
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/add_returns_system.php

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

    // Create returns table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  return_number TEXT UNIQUE NOT NULL,
  status TEXT DEFAULT 'requested',
  reason TEXT,
  refund_amount REAL DEFAULT 0,
  refund_method TEXT,
  refund_date DATETIME,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );

    echo "Returns table created successfully.\n";

    // Create return_items table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS return_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  return_id INTEGER NOT NULL,
  order_item_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  store_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  refund_amount REAL NOT NULL,
  reason TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE,
  FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
  FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE RESTRICT
);
SQL
    );

    echo "Return items table created successfully.\n";

    // Create indexes
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_returns_order ON returns(order_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_returns_user ON returns(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_returns_status ON returns(status);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_return_items_return ON return_items(return_id);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show returns table structure
    $stmt = $pdo->query("PRAGMA table_info(returns);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nReturns table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show return_items table structure
    $stmt = $pdo->query("PRAGMA table_info(return_items);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nReturn items table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nReturns and refunds system ready.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding returns system: " . $e->getMessage() . "\n");
    exit(1);
}