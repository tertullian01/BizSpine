<?php
declare(strict_types=1);

// Add coupon codes table to track discount coupons
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/add_coupons_table.php

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

    // Create coupons table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  discount_type TEXT NOT NULL,
  discount_value REAL NOT NULL,
  min_purchase_amount REAL DEFAULT 0,
  max_uses INTEGER,
  times_used INTEGER DEFAULT 0,
  valid_from DATETIME,
  valid_until DATETIME,
  is_active INTEGER DEFAULT 1,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL
    );

    echo "Coupons table created successfully.\n";

    // Create coupon_usage table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupon_usage (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  coupon_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  order_id INTEGER NOT NULL,
  discount_amount REAL NOT NULL,
  used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
);
SQL
    );

    echo "Coupon usage table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupons_active ON coupons(is_active);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupons_valid_until ON coupons(valid_until);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show coupons table structure
    $stmt = $pdo->query("PRAGMA table_info(coupons);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCoupons table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show coupon_usage table structure
    $stmt = $pdo->query("PRAGMA table_info(coupon_usage);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCoupon usage table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nCoupon system ready. Discount coupons can now be created and tracked.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding coupon tables: " . $e->getMessage() . "\n");
    exit(1);
}