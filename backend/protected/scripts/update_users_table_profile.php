<?php
declare(strict_types=1);

// Add profile fields to users table
// Run from project root:
// php backend/protected/scripts/update_users_table_profile.php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config not found at: $configPath\n");
    exit(1);
}

$config = require $configPath;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "db_path not set in config\n");
    exit(1);
}

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
        'facebook_link' => 'TEXT'
    ];

    foreach ($columns as $column => $type) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $column $type");
            echo "Added column: $column\n";
        } catch (PDOException $e) {
            // Column likely already exists
            if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                echo "Column $column already exists. Skipping.\n";
            } else {
                throw $e;
            }
        }
    }

    echo "Users table updated successfully.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error updating users table: " . $e->getMessage() . "\n");
    exit(1);
}
