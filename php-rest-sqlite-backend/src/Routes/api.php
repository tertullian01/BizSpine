<?php

declare(strict_types=1);

use Slim\App;

/** @var App $app */

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

// Register all routes
\App\Routes\SetupRoutes::register($app);
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
\App\Routes\HealthRoutes::register($app);
