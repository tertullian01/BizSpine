<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\OrderController;
use App\Middleware\AuthMiddleware;
use App\Middleware\OptionalAuthMiddleware;
use Slim\App;

class OrderRoutes
{
    public static function register(App $app): void
    {
        // Public/Optional Auth route for creating orders
        $app->post('/orders', [OrderController::class, 'create'])
            ->add(OptionalAuthMiddleware::class);

        $app->group('/orders', function ($group) {
            $group->get('', [OrderController::class, 'getAll']);
            $group->get('/my', [OrderController::class, 'getMyOrders']);
            $group->get('/{id}', [OrderController::class, 'getById']);
            $group->put('/{id}', [OrderController::class, 'update']);
            $group->delete('/{id}', [OrderController::class, 'delete']);
            $group->post('/{id}/cancel', [OrderController::class, 'cancel']);
            $group->post('/{id}/payment', [OrderController::class, 'addPayment']);
        })->add(AuthMiddleware::class);
    }
}
