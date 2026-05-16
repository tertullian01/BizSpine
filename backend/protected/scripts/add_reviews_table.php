<?php
declare(strict_types=1);

// Add product_reviews table to allow customers to review purchased products
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/add_reviews_table.php

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

    // Create product_reviews table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  order_id INTEGER,
  rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
  review_text TEXT,
  verified INTEGER DEFAULT 0,
  published INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
);
SQL
    );

    echo "Product reviews table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_user ON product_reviews(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_product ON product_reviews(product_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_published ON product_reviews(published);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_verified ON product_reviews(verified);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_rating ON product_reviews(rating);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show product_reviews table structure
    $stmt = $pdo->query("PRAGMA table_info(product_reviews);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nProduct reviews table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nProduct review system ready. Customers can now leave reviews on purchased products.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding product_reviews table: " . $e->getMessage() . "\n");
    exit(1);
}