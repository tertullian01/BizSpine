<?php
declare(strict_types=1);

// Add testimonials table to capture customer testimonials
// Run from project root:
// php backend/protected/scripts/add_testimonials_table.php

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
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Create testimonials table
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS testimonials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_name TEXT NOT NULL,
  customer_email TEXT NOT NULL,
  age_range TEXT,
  testimonial_text TEXT NOT NULL,
  image_url TEXT,
  published INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL
    );

    echo "Testimonials table created successfully.\n";

    // Create indexes for better performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_published ON testimonials(published);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_email ON testimonials(customer_email);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_created ON testimonials(created_at);');

    echo "Indexes created successfully.\n";

    // Verify tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables:\n - " . implode("\n - ", $tables) . "\n";

    // Show testimonials table structure
    $stmt = $pdo->query("PRAGMA table_info(testimonials);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTestimonials table structure:\n";
    foreach ($columns as $col) {
        echo " - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }

    echo "\nTestimonials system ready. Customers can now submit testimonials.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error adding testimonials table: " . $e->getMessage() . "\n");
    exit(1);
}