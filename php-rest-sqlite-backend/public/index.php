<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Services\Database;
use App\Models\BaseModel;
use App\Services\Config;
use App\Services\Container;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Dependencies missing. Run composer install.']);
    exit;
}
require $autoloader;

// Initialize Config service
$config = Config::getInstance();

// Initialize database connection
$dbPath = $config->get('database.database_path');
$db = Database::get($dbPath);
BaseModel::setDatabase($db);

// Create Slim App with DI container
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add the config to the DI container
$container->singleton(Config::class, fn() => $config);

// Bind other services
$container->bind(\App\Services\Validator::class, fn($c) => new \App\Services\Validator());
$container->bind(\App\Services\CacheableProductService::class, fn($c) => new \App\Services\CacheableProductService());
$container->bind(\App\Services\FileUploadService::class, fn($c) => new \App\Services\FileUploadService(
    $c->get(\App\Services\Logger::class),
    $config->get('file_upload', [])
));
$container->bind(\App\Services\PaginationService::class, fn($c) => new \App\Services\PaginationService());
$container->bind(\App\Services\Logger::class, fn($c) => new \App\Services\Logger());
$container->bind(\App\Services\DatabasePool::class, fn($c) => new \App\Services\DatabasePool('sqlite:' . $dbPath, 5));
$container->bind(\App\Services\Metrics::class, fn($c) => new \App\Services\Metrics($c->get(\App\Services\Logger::class)));

// Bind controllers with dependencies
$container->bind(\App\Controllers\StoreController::class, fn($c) => new \App\Controllers\StoreController($c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\BookkeepingController::class, fn($c) => new \App\Controllers\BookkeepingController(null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\TestimonialController::class, fn($c) => new \App\Controllers\TestimonialController(null, null, $c->get(\App\Services\FileUploadService::class)));

// Add Metrics Middleware (must be first to measure all requests)
$app->add(new \App\Middleware\MetricsMiddleware($container->get(\App\Services\Metrics::class)));

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Add Security Headers Middleware
$app->add(new \App\Middleware\SecurityHeadersMiddleware([
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000',
]));

// Add File Upload Middleware
$app->add(new \App\Middleware\FileUploadMiddleware(
    $container->get(\App\Services\FileUploadService::class),
    $container->get(\App\Services\Logger::class),
    $config->get('file_upload_middleware', [])
));

// Load app routes
require __DIR__ . '/../src/Routes/api.php';

// Run app
$app->run();