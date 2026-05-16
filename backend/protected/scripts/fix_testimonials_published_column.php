<?php
declare(strict_types=1);

// Fix testimonials table - add missing 'published' column
// Run from project root:
// php backend/protected/scripts/fix_testimonials_published_column.php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config not found at: $configPath\n");
    exit(1);
}

$config = require $configPath;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "db_path not set in config (expected 'db_path' or 'database.database_path')\n");
    exit(1);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if testimonials table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='testimonials';");
    if (!$stmt->fetch()) {
        fwrite(STDERR, "Testimonials table does not exist. Run add_testimonials_table.php first.\n");
        exit(1);
    }

    // Check if 'published' column already exists
    $stmt = $pdo->query("PRAGMA table_info(testimonials);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (in_array('published', $columnNames)) {
        echo "Column 'published' already exists in testimonials table.\n";
    } else {
        // Add the missing 'published' column
        $pdo->exec("ALTER TABLE testimonials ADD COLUMN published INTEGER DEFAULT 0;");
        echo "Added 'published' column to testimonials table.\n";

        // Create index for the new column
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_published ON testimonials(published);');
        echo "Created index on 'published' column.\n";
    }

    // Show current table structure
    $stmt = $pdo->query("PRAGMA table_info(testimonials);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCurrent testimonials table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nFix completed successfully.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}