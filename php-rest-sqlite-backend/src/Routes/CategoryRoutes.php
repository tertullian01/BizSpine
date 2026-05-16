<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\CategoryController;
use Slim\App;

class CategoryRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();
        $app->group('/bookkeeping/categories', function ($group) {
            $group->get('', [CategoryController::class, 'getAll']);
            $group->get('/{id}', [CategoryController::class, 'getById']);
            $group->post('', [CategoryController::class, 'create']);
            $group->put('/{id}', [CategoryController::class, 'update']);
            $group->delete('/{id}', [CategoryController::class, 'delete']);
        })->add($staff);
    }
}