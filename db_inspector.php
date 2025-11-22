<?php
$dbPath = __DIR__ . '/php-rest-sqlite-backend/protected/db/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all tables
$tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_ %'");
$tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "--- Schema for table: $table ---
";
    $schemaQuery = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
    $schema = $schemaQuery->fetch(PDO::FETCH_COLUMN);
    echo $schema . "\n\n";

    echo "--- Data for table: $table ---
";
    $dataQuery = $db->query("SELECT * FROM $table");
    $data = $dataQuery->fetchAll(PDO::FETCH_ASSOC);
    if (empty($data)) {
        echo "No data in this table.\n";
    } else {
        foreach ($data as $row) {
            print_r($row);
        }
    }
    echo "\n";
}

