<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\UserController;
use Slim\App;

class UserRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();
        $app->group('/users', function ($group) {
            $group->get('', [UserController::class, 'getAllUsers']);
            $group->get('/customers', [UserController::class, 'getCustomers']);
            $group->get('/{id}', [UserController::class, 'getUser']);
            $group->post('', [UserController::class, 'createUser']);
            $group->put('/{id}', [UserController::class, 'updateUser']);
            $group->delete('/{id}', [UserController::class, 'deleteUser']);
            $group->put('/{id}/password', [UserController::class, 'updateUserPassword']);
        })->add($staff);

        // Clients routes - customer data management
        $app->get('/clients', [UserController::class, 'getClients'])->add($staff);
        $app->get('/clients/{id}', [UserController::class, 'getClient'])->add($staff);
        $app->put('/clients/{id}', [UserController::class, 'updateClient'])->add($staff);
        $app->put('/clients/{id}/password', [UserController::class, 'updateClientPassword'])->add($staff);
    }
}
