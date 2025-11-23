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
        'allowed_origins' => ['https://test.nakednettle.com', 'https://nakednettle.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'allow_credentials' => true,
    ],
    'email' => [
        'smtp' => [
            'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
            'port' => getenv('SMTP_PORT') ?: 587,
            'username' => getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_PASSWORD') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        ],
        'from' => [
            'email' => getenv('FROM_EMAIL') ?: 'noreply@example.com',
            'name' => getenv('FROM_NAME') ?: 'Small Business App',
        ],
    ],
];