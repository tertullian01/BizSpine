<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ProductController;
use Slim\App;

class ProductRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();

        $app->get('/products', [ProductController::class, 'getAll']);
        $app->get('/products/type/{type}', [ProductController::class, 'getByType']);
        $app->get('/products/types', [ProductController::class, 'getUniqueTypes']);
        $app->get('/products/{id}', [ProductController::class, 'getById']);
        $app->post('/products', [ProductController::class, 'create'])->add($staff);
        $app->put('/products/{id}', [ProductController::class, 'update'])->add($staff);
        $app->delete('/products/{id}', [ProductController::class, 'delete'])->add($staff);
    }
}
