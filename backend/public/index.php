<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Services\Database;
use App\Models\BaseModel;
use App\Services\Config;
use App\Services\Container;
use App\Routes\RouteSecurity;

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

// Initialize database connection
$dbPath = $config->get('database.database_path');
$db = Database::get($dbPath);
BaseModel::setDatabase($db);

// Create Slim App with DI container
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Subdirectory deploy: public_html/BizSpine/api/index.php → strip /BizSpine/api from URIs
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (str_ends_with($scriptName, '/api/index.php')) {
    $apiBasePath = dirname($scriptName);
    if ($apiBasePath !== '/' && $apiBasePath !== '.') {
        $app->setBasePath($apiBasePath);
    }
}

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
$jwtSecret = RouteSecurity::jwtSecret();
$container->bind(\App\Middleware\AuthMiddleware::class, fn($c) => new \App\Middleware\AuthMiddleware($jwtSecret));
$container->bind(\App\Middleware\OptionalAuthMiddleware::class, fn($c) => new \App\Middleware\OptionalAuthMiddleware($jwtSecret));

// Bind controllers with dependencies
$container->bind(\App\Controllers\AuthController::class, fn($c) => new \App\Controllers\AuthController($config->getAll(), $c->get(\App\Services\EmailService::class)));
$container->bind(\App\Controllers\SetupController::class, fn($c) => new \App\Controllers\SetupController($config->getAll()));
$container->bind(\App\Controllers\StoreController::class, fn($c) => new \App\Controllers\StoreController($c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\BookkeepingController::class, fn($c) => new \App\Controllers\BookkeepingController(null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\TestimonialController::class, fn($c) => new \App\Controllers\TestimonialController(null, null, $c->get(\App\Services\FileUploadService::class)));
$container->bind(\App\Controllers\ProductController::class, fn($c) => new \App\Controllers\ProductController($db, $c->get(\App\Services\CacheableProductService::class), $c->get(\App\Services\Logger::class), $c->get(\App\Services\PaginationService::class)));
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
$container->bind(\App\Controllers\EmailLogController::class, fn($c) => new \App\Controllers\EmailLogController($c->get(\App\Services\PaginationService::class)));
$container->bind(\App\Controllers\EmailTemplateController::class, fn($c) => new \App\Controllers\EmailTemplateController($db));
$container->bind(\App\Controllers\HealthController::class, fn($c) => new \App\Controllers\HealthController($c->get(Config::class)->getAll()));

// Slim runs middleware in reverse registration order (last added = outermost / first on request).
// Innermost first: metrics → body → upload → security headers → routing → error → CORS.

$app->add(new \App\Middleware\MetricsMiddleware($container->get(\App\Services\Metrics::class)));

$app->add(new \App\Middleware\FileUploadMiddleware(
    $container->get(\App\Services\FileUploadService::class),
    $container->get(\App\Services\Logger::class),
    $config->get('file_upload_middleware', [])
));

$app->addBodyParsingMiddleware();

$app->add(new \App\Middleware\SecurityHeadersMiddleware([
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000',
]));

$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    $config->get('environment.debug', false),
    true,
    true,
    $container->get(\App\Services\Logger::class)
);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
if ($errorHandler instanceof \Slim\Handlers\ErrorHandler) {
    $errorHandler->forceContentType('application/json');
}

// Outermost: must answer OPTIONS preflight before RoutingMiddleware (POST-only routes reject OPTIONS).
$corsConfig = $config->get('cors', []);
$allowedOrigins = $corsConfig['allowed_origins'] ?? [];

$app->add(new \App\Middleware\CorsMiddleware(
    allowedOrigins: $allowedOrigins,
    allowedMethods: $corsConfig['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: $corsConfig['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'],
    allowCredentials: $corsConfig['allow_credentials'] ?? true
));

// Load app routes
require __DIR__ . '/../src/Routes/api.php';
require __DIR__ . '/../src/Routes/EmailTemplateRoutes.php';
require __DIR__ . '/../src/Routes/EmailLogRoutes.php';
\App\Routes\EmailLogRoutes::register($app);

if ($config->get('security.allow_insecure_setup', false)) {

    // Endpoint to retrieve database design
    $app->get('/db-design', function ($request, $response) use ($db) {
        $design = [];
        $tables = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $design[$tableName] = [
                'create_statement' => $table['sql'],
                'columns' => $db->query('PRAGMA table_info("' . str_replace('"', '""', $tableName) . '")')->fetchAll(PDO::FETCH_ASSOC),
            ];
        }

        $response->getBody()->write(json_encode($design, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Endpoint to run database migrations
    $app->post('/run_migrations', function ($request, $response) {
        try {
            $projectRoot = dirname(__DIR__);
            chdir($projectRoot);

            $phinxApp = new \Phinx\Console\PhinxApplication();
            $phinxApp->setAutoExit(false);

            $input = new \Symfony\Component\Console\Input\ArrayInput(['command' => 'migrate']);
            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            $returnVar = $phinxApp->run($input, $output);
            $outputText = $output->fetch();

            $payload = ['success' => $returnVar === 0, 'output' => $outputText];
            if ($returnVar !== 0) {
                $payload['error'] = 'Migration failed. Output: ' . $outputText;
            }

            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($returnVar === 0 ? 200 : 500);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
}

// Run app
$app->run();