<?php
$config = require __DIR__ . '/../protected/config/config.php';
$dbPath = $config['database']['database_path'];
$db = new PDO("sqlite:$dbPath");
$stmt = $db->query("PRAGMA table_info(users)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "{$col['name']} ({$col['type']}) - NotNull: {$col['notnull']} - Default: {$col['dflt_value']}\n";
}
