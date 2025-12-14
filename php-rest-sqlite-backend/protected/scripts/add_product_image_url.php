<?php

/**
 * Database Migration Script
 * Adds image_url column to products table
 * 
 * Usage: php protected/scripts/add_product_image_url.php
 */

// Define database path relative to this script
$dbPath = __DIR__ . '/../db/database.sqlite';

if (!file_exists($dbPath)) {
    echo "Error: Database file not found at $dbPath" . PHP_EOL;
    exit(1);
}

echo "Target Database: " . realpath($dbPath) . PHP_EOL;

try {
    // Connect to SQLite database
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current table info
    echo "Checking schema for 'products' table..." . PHP_EOL;
    $stmt = $pdo->query("PRAGMA table_info(products)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    // Check if image_url exists
    if (in_array('image_url', $columnNames)) {
        echo "Info: Column 'image_url' already exists." . PHP_EOL;
    } else {
        // Add the column
        echo "Adding 'image_url' column..." . PHP_EOL;
        $pdo->exec("ALTER TABLE products ADD COLUMN image_url TEXT");
        echo "Success: Column 'image_url' added to 'products' table." . PHP_EOL;
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}