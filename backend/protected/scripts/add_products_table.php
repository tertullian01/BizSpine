<?php
$dbPath = __DIR__ . '/../db/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT,
    description TEXT,
    featured_ingredients TEXT,
    all_ingredients TEXT,
    size TEXT,
    cost REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
";

$db->exec($sql);

// Create indexes for better performance
$db->exec('CREATE INDEX IF NOT EXISTS idx_products_type ON products(type);');
$db->exec('CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);');

echo "Products table created successfully.\n";
echo "Indexes created successfully.\n";

