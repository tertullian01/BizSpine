<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec('ALTER TABLE users ADD COLUMN role TEXT DEFAULT "customer"');
    echo "Role field added to users table.\n";
    
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
    echo "Index created.\n";
    
    echo "User roles system ready. Roles: customer, employee, admin\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}