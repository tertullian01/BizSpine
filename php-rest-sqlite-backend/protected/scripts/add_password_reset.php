<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec('ALTER TABLE users ADD COLUMN reset_token TEXT');
    echo "reset_token field added to users table.\n";
    
    $pdo->exec('ALTER TABLE users ADD COLUMN reset_expires_at DATETIME');
    echo "reset_expires_at field added to users table.\n";
    
    echo "Password reset system ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}