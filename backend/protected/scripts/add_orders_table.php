<?php
declare(strict_types=1);

// Add orders and order_items tables to track customer purchases
// Run from project root:
// php backend/protected/scripts/add_orders_table.php

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

    // Create orders table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  order_number TEXT UNIQUE NOT NULL,
  order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  fulfillment_status TEXT DEFAULT 'pending',
  shipping_date DATETIME,
  shipping_address TEXT NOT NULL,
  phone_number TEXT,
  whatsapp_number TEXT,
  subtotal REAL NOT NULL DEFAULT 0,
  discount_amount REAL DEFAULT 0,
  coupon_code TEXT,
  shipping_cost REAL DEFAULT 0,
  total REAL NOT NULL DEFAULT 0,
  tracking_number TEXT,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );

    echo "Orders table created successfully.\n";

    // Create order_items table (line items)
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  store_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  unit_price REAL NOT NULL,
  subtotal REAL NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
  FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE RESTRICT
);
SQL
    );

    echo "Order items table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(fulfillment_status);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(order_date);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items(product_id);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show orders table structure
    $stmt = $pdo->query("PRAGMA table_info(orders);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nOrders table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    // Show order_items table structure
    $stmt = $pdo->query("PRAGMA table_info(order_items);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nOrder items table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nOrders system ready. Users can now place orders through the API.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding orders tables: " . $e->getMessage() . "\n");
    exit(1);
}