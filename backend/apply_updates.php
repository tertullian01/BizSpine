<?php

require __DIR__ . '/vendor/autoload.php';

// Load configuration to get the correct DB path
$config = require __DIR__ . '/protected/config/config.php';
$dbPath = $config['database']['database_path'];

echo "Target Database: " . $dbPath . "\n";

// Ensure the database directory exists
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    echo "Creating database directory: $dbDir\n";
    mkdir($dbDir, 0777, true);
}

// Directory containing SQL scripts
$scriptsDir = __DIR__ . '/protected/scripts';

if (!is_dir($scriptsDir)) {
    die("Scripts directory not found: " . $scriptsDir . "\n");
}

// Get all .php files and sort them to ensure execution order
$files = glob($scriptsDir . '/*.php');
sort($files);

if (empty($files)) {
    echo "No PHP scripts found in " . $scriptsDir . "\n";
    exit;
}

// Files to exclude from automatic execution
$excludedFiles = [
    'clear_database.php', // Destructive script
];

foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $excludedFiles)) {
        echo "Skipping excluded file: $filename\n";
        continue;
    }

    echo "Running " . $filename . "...\n";
    
    // Execute the PHP script in a separate process
    passthru("php " . escapeshellarg($file), $returnVar);
    
    if ($returnVar !== 0) {
        echo "Failed to run $filename\n";
        exit(1);
    }
}

echo "All updates applied successfully.\n";