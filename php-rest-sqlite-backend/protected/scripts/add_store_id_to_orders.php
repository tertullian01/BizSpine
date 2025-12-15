<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec('ALTER TABLE orders ADD COLUMN store_id INTEGER REFERENCES stores(id) ON DELETE SET NULL');
    echo "store_id field added to orders table.\n";
    
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_store_id ON orders(store_id)');
    echo "Index created.\n";
    
    echo "Store ID added to orders successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}