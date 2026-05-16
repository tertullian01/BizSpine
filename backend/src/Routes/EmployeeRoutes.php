<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\EmployeeController;
use Slim\App;

class EmployeeRoutes
{
    public static function register(App $app): void
    {
        $admin = RouteSecurity::requireAdmin();
        $app->group('/employees', function ($group) {
            $group->get('', [EmployeeController::class, 'getAll']);
            $group->post('', [EmployeeController::class, 'create']);
            $group->put('/{id}', [EmployeeController::class, 'update']);
            $group->delete('/{id}', [EmployeeController::class, 'delete']);
        })->add($admin);
    }
}
