<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class UserRoutes
{
    public static function register(App $app): void
    {
        $app->group('/users', function ($group) {
            $group->get('', [UserController::class, 'getAllUsers']);
            $group->get('/customers', [UserController::class, 'getCustomers']);
            $group->get('/{id}', [UserController::class, 'getUser']);
            $group->post('', [UserController::class, 'createUser']);
            $group->put('/{id}', [UserController::class, 'updateUser']);
            $group->delete('/{id}', [UserController::class, 'deleteUser']);
        })->add(AuthMiddleware::class);
    }
}
