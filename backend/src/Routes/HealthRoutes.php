<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\HealthController;
use Slim\App;

class HealthRoutes
{
    public static function register(App $app): void
    {
        $app->get('/', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'app' => 'BizSpine API',
                'health' => 'GET /health',
            ], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });
        $app->get('/health', [HealthController::class, 'index']);
    }
}
