<?php
// Configuration settings for the application

$config = [
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
        'issuer' => 'bizspine.local',
        'access_exp' => 900,
        'refresh_exp' => 604800,
    ],
    'file_upload' => [
        'max_file_size' => 5 * 1024 * 1024, // 5MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        'upload_path' => __DIR__ . '/../../uploads/',
        'create_thumbnails' => true,
        'thumbnail_size' => [150, 150],
        'secure_filename' => true,
    ],
    'file_upload_middleware' => [
        'max_files' => 10,
        'allowed_fields' => [], // Empty array means all fields allowed
    ],
    'cors' => [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
        'allow_credentials' => true,
    ],
    // Setup, system/import, /db-design, POST /run_migrations — NEVER enable on public hosts.
    'security' => [
        'allow_insecure_setup' => false,
    ],
];

$localPath = __DIR__ . '/install_local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;