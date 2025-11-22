<?php
declare(strict_types=1);

// Add referral program tables to track user referrals and rewards
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/add_referral_tables.php

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

    // Create user_referrals table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_referrals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL UNIQUE,
  referral_code TEXT NOT NULL UNIQUE,
  times_used INTEGER DEFAULT 0,
  points_earned INTEGER DEFAULT 0,
  points_redeemed INTEGER DEFAULT 0,
  points_balance INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );

    echo "User referrals table created successfully.\n";

    // Create referral_usage table to track each referral use
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS referral_usage (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  referrer_user_id INTEGER NOT NULL,
  referred_user_id INTEGER NOT NULL,
  referral_code TEXT NOT NULL,
  order_id INTEGER,
  points_awarded INTEGER DEFAULT 0,
  used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(referred_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
);
SQL
    );

    echo "Referral usage table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_referrals_user ON user_referrals(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_referrals_code ON user_referrals(referral_code);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_referrer ON referral_usage(referrer_user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_referred ON referral_usage(referred_user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_code ON referral_usage(referral_code);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show user_referrals table structure
    $stmt = $pdo->query("PRAGMA table_info(user_referrals);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nUser referrals table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show referral_usage table structure
    $stmt = $pdo->query("PRAGMA table_info(referral_usage);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nReferral usage table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nReferral program system ready. Users can now earn points by referring new customers.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding referral tables: " . $e->getMessage() . "\n");
    exit(1);
}