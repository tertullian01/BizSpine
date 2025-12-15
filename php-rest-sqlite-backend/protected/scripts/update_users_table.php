<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $columns = [
        'first_name' => 'TEXT',
        'last_name' => 'TEXT',
        'country' => 'TEXT',
        'street_line_1' => 'TEXT',
        'street_line_2' => 'TEXT',
        'city' => 'TEXT',
        'state' => 'TEXT',
        'postal_code' => 'TEXT',
        'mobile_number' => 'TEXT',
        'whatsapp_number' => 'TEXT',
        'instagram_link' => 'TEXT',
        'facebook_link' => 'TEXT',
        'is_email_verified' => 'INTEGER DEFAULT 0',
        'last_login' => 'DATETIME'
    ];

    foreach ($columns as $column => $type) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $column $type");
            echo "Added column $column to users table.\n";
        } catch (PDOException $e) {
            // Ignore error if column already exists
            if (strpos($e->getMessage(), 'duplicate column name') === false) {
                echo "Note: Could not add column $column (might already exist).\n";
            }
        }
    }

    echo "Users table update completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}