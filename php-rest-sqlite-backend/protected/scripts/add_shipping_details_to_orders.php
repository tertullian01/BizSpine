<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_carrier TEXT");
    echo "Added 'shipping_carrier' column to orders table.\n";

    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_method TEXT");
    echo "Added 'shipping_method' column to orders table.\n";
    
    echo "Shipping details columns added successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}