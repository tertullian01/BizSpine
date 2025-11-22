<?php

$config = require __DIR__ . '/protected/config/config.php';
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;

if (!$dbPath) {
    throw new \Exception("Database path not found in config.php");
}

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => $dbPath,
            'suffix' => ''
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name' => __DIR__ . '/protected/database/testing',
            'suffix' => ''
        ]
    ],
    'version_order' => 'creation'
];