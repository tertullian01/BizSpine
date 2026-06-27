<?php

declare(strict_types=1);

use App\Services\Config;
use Slim\App;

/** @var App $app */

$exposeDangerous = Config::getInstance()->get('security.allow_insecure_setup', false);
$backendRoot = dirname(__DIR__, 2);

// CORS test route
$app->get('/cors-test', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'success' => true,
        'data' => [
            'message' => 'CORS is working!',
            'status' => 'success',
            'method' => $request->getMethod(),
            'origin' => $request->getHeaderLine('Origin') ?: 'none'
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Always available: DB check + Phinx migrations for setup.html on existing installs.
\App\Routes\SetupRoutes::registerMaintenance($app);

$app->get('/system/status', function ($request, $response) use ($exposeDangerous, $backendRoot) {
    $envPath = $backendRoot . '/.env';
    $response->getBody()->write(json_encode([
        'success' => true,
        'data' => [
            'app' => 'BizSpine API',
            'setup_routes_enabled' => (bool) $exposeDangerous,
            'maintenance_setup_routes' => true,
            'env_file' => [
                'path' => $envPath,
                'exists' => is_file($envPath),
            ],
            'allow_insecure_setup_env' => $_ENV['ALLOW_INSECURE_SETUP'] ?? getenv('ALLOW_INSECURE_SETUP') ?: null,
        ],
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// Full setup wizard (admin, imports) only when ALLOW_INSECURE_SETUP=true.
if ($exposeDangerous) {
    \App\Routes\SetupRoutes::register($app);
}

\App\Routes\ProductRoutes::register($app);
\App\Routes\OrderRoutes::register($app);
\App\Routes\AuthRoutes::register($app);
\App\Routes\StoreRoutes::register($app);
\App\Routes\InventoryRoutes::register($app);
\App\Routes\ReviewRoutes::register($app);
\App\Routes\TestimonialRoutes::register($app);
\App\Routes\BookkeepingRoutes::register($app);
\App\Routes\ReferralRoutes::register($app);
\App\Routes\CouponRoutes::register($app);
\App\Routes\TaxRoutes::register($app);
\App\Routes\ReturnRoutes::register($app);
\App\Routes\UserRoutes::register($app);
\App\Routes\EmployeeRoutes::register($app);
\App\Routes\HealthRoutes::register($app);
if ($exposeDangerous) {
    \App\Routes\SystemRoutes::register($app);
}
\App\Routes\CategoryRoutes::register($app);
\App\Routes\SettingsRoutes::register($app);
$app->post('/contact', [\App\Controllers\ContactController::class, 'send']);
$app->post('/contact/bulk', [\App\Controllers\ContactController::class, 'sendBulkOrder']);

$app->get('/system/ping', function ($request, $response) {
    $response->getBody()->write('pong');
    return $response;
});
