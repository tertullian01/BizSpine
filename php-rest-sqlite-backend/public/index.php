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

// Enable PHP error logging
ini_set('error_log', __DIR__ . '/logs/debug.log');
ini_set('log_errors', '1');

// CORS headers for shared hosting - handle OPTIONS requests
error_log("Request: " . $_SERVER['REQUEST_METHOD'] . " to " . $_SERVER['REQUEST_URI'] . " from " . ($_SERVER['HTTP_ORIGIN'] ?? 'no-origin'));
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("Handling OPTIONS request to: " . $_SERVER['REQUEST_URI']);
    header('Access-Control-Allow-Origin: https://test.nakednettle.com');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit(0);
}

// Set CORS headers for all other requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://test.nakednettle.com', 'https://nakednettle.com'];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 86400');

// TEMPORARY: Simple response to test if index.php is reached
if ($_SERVER['REQUEST_URI'] === '/cors-test') {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Index.php reached for /cors-test',
        'method' => $_SERVER['REQUEST_METHOD'],
        'time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

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
$container->bind(\App\Services\EmailService::class, fn($c) => new \App\Services\EmailService(
    $config->get('email', []),
    $c->get(\App\Services\Logger::class)
));

// Bind middleware
$container->bind(\App\Middleware\AuthMiddleware::class, fn($c) => new \App\Middleware\AuthMiddleware($config->get('jwt.secret')));

// Bind controllers with dependencies
$container->bind(\App\Controllers\StoreController::class, fn($c) => new \App\Controllers\StoreController($c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\BookkeepingController::class, fn($c) => new \App\Controllers\BookkeepingController(null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\TestimonialController::class, fn($c) => new \App\Controllers\TestimonialController(null, null, $c->get(\App\Services\FileUploadService::class)));

// Add Metrics Middleware (must be first to measure all requests)
$app->add(new \App\Middleware\MetricsMiddleware($container->get(\App\Services\Metrics::class)));

// Add CORS Middleware
$corsConfig = $config->get('cors', []);
$app->add(new \App\Middleware\CorsMiddleware(
    allowedOrigins: $corsConfig['allowed_origins'] ?? ['*'],
    allowedMethods: $corsConfig['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: $corsConfig['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'],
    allowCredentials: $corsConfig['allow_credentials'] ?? true
));

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