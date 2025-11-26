<?php
$config = require __DIR__ . '/../protected/config/config.php';
$dbPath = $config['database']['database_path'];
echo "Checking DB at: $dbPath\n";

if (!file_exists($dbPath)) {
    echo "File not found.\n";
    exit(1);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
