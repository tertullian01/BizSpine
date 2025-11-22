<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\StoreController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class StoreRoutes
{
    public static function register(App $app): void
    {
        $app->get('/stores', [StoreController::class, 'getAll']);
        $app->get('/stores/{id}', [StoreController::class, 'getById']);
        $app->post('/stores', [StoreController::class, 'create'])->add(AuthMiddleware::class);
        $app->put('/stores/{id}', [StoreController::class, 'update'])->add(AuthMiddleware::class);
        $app->delete('/stores/{id}', [StoreController::class, 'delete'])->add(AuthMiddleware::class);
        $app->post('/stores/{id}/upload-logo', [StoreController::class, 'uploadLogo'])->add(AuthMiddleware::class);
    }
}
