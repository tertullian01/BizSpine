<?php

// Load the main application config to get the database path
$appConfig = require __DIR__ . '/protected/config/config.php';

// Get the database path from the app config
$dbPath = $appConfig['database']['database_path'];

// The testing database path (used for local testing, but good to have)
$testingDbPath = __DIR__ . '/protected/database/testing.db';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'sqlite',
            'name' => $dbPath,
            'suffix' => '',
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name' => $testingDbPath,
            'suffix' => '',
        ]
    ],
    'version_order' => 'creation'
];