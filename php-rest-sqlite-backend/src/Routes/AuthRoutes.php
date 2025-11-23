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
        $app->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        $app->post('/auth/forgot_password', [AuthController::class, 'forgotPassword']); // Alias for underscore
        $app->post('/auth/reset-password', [AuthController::class, 'resetPassword']);
        $app->post('/auth/reset_password', [AuthController::class, 'resetPassword']); // Alias for underscore
    }
}
