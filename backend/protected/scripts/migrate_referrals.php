<?php

/**
 * Database Migration Script for Referral System Updates
 * 
 * This script adds the following columns to the user_referrals table:
 * - discount_type (TEXT)
 * - discount_amount (REAL)
 * - status (TEXT)
 * 
 * Usage: php protected/scripts/migrate_referrals.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Services\Database;

echo "Starting migration for referral fields...\n";

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Error: Config file not found at $configFile\n");
}

$config = require $configFile;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;

if (!$dbPath) {
    die("Error: Database path not configured.\n");
}

echo "Using database configuration path: $dbPath\n";

try {
    // Get database connection
    $db = Database::get($dbPath);
    
    // Check user_referrals table columns
    $stmt = $db->query("PRAGMA table_info(user_referrals);");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    $migrations = [];
    
    if (!in_array('discount_type', $columnNames)) {
        $db->exec("ALTER TABLE user_referrals ADD COLUMN discount_type TEXT DEFAULT 'percentage';");
        $migrations[] = "Added 'discount_type' column";
    }
    
    if (!in_array('discount_amount', $columnNames)) {
        $db->exec("ALTER TABLE user_referrals ADD COLUMN discount_amount REAL DEFAULT 10.0;");
        $migrations[] = "Added 'discount_amount' column";
    }
    
    if (!in_array('status', $columnNames)) {
        $db->exec("ALTER TABLE user_referrals ADD COLUMN status TEXT DEFAULT 'active';");
        $migrations[] = "Added 'status' column";
    }
    
    if (empty($migrations)) {
        echo "No migrations needed. Table is up to date.\n";
    } else {
        echo "Migrations applied successfully:\n";
        foreach ($migrations as $msg) {
            echo "- $msg\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error running migration: " . $e->getMessage() . "\n";
    exit(1);
}