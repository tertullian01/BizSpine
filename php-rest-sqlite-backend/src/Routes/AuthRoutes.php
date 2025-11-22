<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class AuthRoutes
{
    public static function register(App $app): void
    {
        $app->post('/auth/register', [AuthController::class, 'register']);
        $app->post('/auth/login', [AuthController::class, 'login']);
        $app->post('/auth/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
        $app->post('/auth/refresh', [AuthController::class, 'refresh'])->add(AuthMiddleware::class);
    }
}
