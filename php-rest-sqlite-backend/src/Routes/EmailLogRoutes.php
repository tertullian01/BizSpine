<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\EmailLogController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class EmailLogRoutes
{
    public static function register(App $app): void
    {
        $app->group('/email-logs', function (RouteCollectorProxy $group) {
            $group->get('', [EmailLogController::class, 'getAll']);
        })->add(AuthMiddleware::class);

        // Alias for singular access
        $app->get('/email-log', [EmailLogController::class, 'getAll'])->add(AuthMiddleware::class);
    }
}