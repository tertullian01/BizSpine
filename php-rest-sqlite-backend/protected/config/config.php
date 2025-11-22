<?php
// Configuration settings for the application

return [
    'database' => [
        'driver' => 'sqlite',
        'database_path' => __DIR__ . '/../db/database.sqlite',
    ],
    'environment' => [
        'app_name' => 'PHP REST SQLite Backend',
        'debug' => true,
    ],
    'api' => [
        'version' => '1.0',
        'base_url' => '/api',
    ],
    'jwt' => [
        // Load from environment in production. Change fallback for dev only.
        'secret' => getenv('JWT_SECRET') ?: 'dev-fallback-change-me',
        'issuer' => 'smallbusiness.local',
        'access_exp' => 900,
        'refresh_exp' => 604800,
    ],
];