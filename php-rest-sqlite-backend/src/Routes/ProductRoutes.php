<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ProductController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class ProductRoutes
{
    public static function register(App $app): void
    {
        $app->get('/products', [ProductController::class, 'getAll']);
        $app->get('/products/{id}', [ProductController::class, 'getById']);
        $app->post('/products', [ProductController::class, 'create'])->add(AuthMiddleware::class);
        $app->put('/products/{id}', [ProductController::class, 'update'])->add(AuthMiddleware::class);
        $app->delete('/products/{id}', [ProductController::class, 'delete'])->add(AuthMiddleware::class);
    }
}
