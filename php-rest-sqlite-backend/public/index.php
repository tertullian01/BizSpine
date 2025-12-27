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

// CORS is now handled by CorsMiddleware - removing duplicate PHP headers


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

// Handle CORS headers in PHP for shared hosting compatibility
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = $config->get('cors.allowed_origins', []);
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

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
$container->bind(\App\Services\EmailService::class, fn($c) => new \App\Services\EmailService($db, $c->get(\App\Services\Logger::class)));

// Bind middleware
$container->bind(\App\Middleware\AuthMiddleware::class, fn($c) => new \App\Middleware\AuthMiddleware($config->get('jwt.secret')));

// Bind controllers with dependencies
$container->bind(\App\Controllers\AuthController::class, fn($c) => new \App\Controllers\AuthController($config->getAll(), $c->get(\App\Services\EmailService::class)));
$container->bind(\App\Controllers\SetupController::class, fn($c) => new \App\Controllers\SetupController($config->getAll()));
$container->bind(\App\Controllers\StoreController::class, fn($c) => new \App\Controllers\StoreController($c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\BookkeepingController::class, fn($c) => new \App\Controllers\BookkeepingController(null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\TestimonialController::class, fn($c) => new \App\Controllers\TestimonialController(null, null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\ProductController::class, fn($c) => new \App\Controllers\ProductController($c->get(\App\Services\CacheableProductService::class), $c->get(\App\Services\Logger::class), $c->get(\App\Services\PaginationService::class)));
$container->bind(\App\Controllers\OrderController::class, fn($c) => new \App\Controllers\OrderController(
    $db, 
    $c->get(\App\Services\PaginationService::class),
    $c->get(\App\Services\EmailService::class)
));
$container->bind(\App\Controllers\InventoryController::class, fn($c) => new \App\Controllers\InventoryController($db));
$container->bind(\App\Controllers\ReviewController::class, fn($c) => new \App\Controllers\ReviewController($db));
$container->bind(\App\Controllers\CouponController::class, fn($c) => new \App\Controllers\CouponController($db));
$container->bind(\App\Controllers\ReferralController::class, fn($c) => new \App\Controllers\ReferralController());
$container->bind(\App\Controllers\TaxController::class, fn($c) => new \App\Controllers\TaxController($c->get(\App\Services\PaginationService::class)));
$container->bind(\App\Controllers\ReturnController::class, fn($c) => new \App\Controllers\ReturnController($db));
$container->bind(\App\Controllers\EmployeeController::class, fn($c) => new \App\Controllers\EmployeeController());
$container->bind(\App\Controllers\SystemController::class, fn($c) => new \App\Controllers\SystemController($db));
$container->bind(\App\Controllers\ClientController::class, fn($c) => new \App\Controllers\ClientController($db));
$container->bind(\App\Controllers\CategoryController::class, fn($c) => new \App\Controllers\CategoryController());
$container->bind(\App\Controllers\SettingsController::class, fn($c) => new \App\Controllers\SettingsController(
    $db, 
    $c->get(\App\Services\FileUploadService::class),
    $c->get(\App\Services\EmailService::class),
    $c->get(\App\Services\Logger::class)
));
$container->bind(\App\Controllers\EmailTemplateController::class, fn($c) => new \App\Controllers\EmailTemplateController($db));

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

// Add Body Parsing Middleware (Must run before FileUploadMiddleware to ensure parsed body is available and not overwritten)
$app->addBodyParsingMiddleware();

// Load app routes
require __DIR__ . '/../src/Routes/api.php';
require __DIR__ . '/../src/Routes/EmailTemplateRoutes.php';

// Endpoint to retrieve database design
$app->get('/db-design', function ($request, $response) use ($db) {
    $design = [];
    $tables = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $table) {
        $tableName = $table['name'];
        $design[$tableName] = [
            'create_statement' => $table['sql'],
            'columns' => $db->query("PRAGMA table_info(\"$tableName\")")->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    $response->getBody()->write(json_encode($design, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Run app
$app->run();