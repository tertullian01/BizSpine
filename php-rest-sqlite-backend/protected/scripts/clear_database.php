<?php
// Script to clear all data from the database while preserving schema
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;
$dbPath = $config['database']['database_path'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Clearing data from " . count($tables) . " tables...\n";

    // Disable foreign keys temporarily
    $pdo->exec('PRAGMA foreign_keys = OFF;');

    // Delete all rows from each table
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM $table");
        echo "Cleared table: $table\n";
    }

    // Reset sequences
    $pdo->exec("DELETE FROM sqlite_sequence");
    echo "Reset auto-increment sequences\n";

    // Re-enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON;');

    echo "\nDatabase cleared successfully. All tables are now empty.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
