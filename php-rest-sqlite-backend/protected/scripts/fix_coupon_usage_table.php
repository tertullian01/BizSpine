<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='coupon_usage'");
    if (!$stmt->fetch()) {
        $pdo->exec(<<<'SQL'
        CREATE TABLE coupon_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coupon_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            order_id INTEGER DEFAULT 0,
            discount_amount REAL NOT NULL,
            used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        SQL);
        echo "Created 'coupon_usage' table.\n";
        
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id)');
        echo "Created indexes for 'coupon_usage'.\n";
    } else {
        echo "'coupon_usage' table already exists.\n";
    }
    
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}