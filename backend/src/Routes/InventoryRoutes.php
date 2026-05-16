<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\InventoryController;
use Slim\App;

class InventoryRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();

        $app->get('/inventory', [InventoryController::class, 'getAll']);
        $app->get('/inventory/low-stock', [InventoryController::class, 'getLowStock']);
        $app->get('/inventory/{id}', [InventoryController::class, 'getById']);
        $app->get('/inventory/product/{id}', [InventoryController::class, 'getByProduct']);
        $app->get('/inventory/store/{id}', [InventoryController::class, 'getByStore']);
        $app->post('/inventory', [InventoryController::class, 'create'])->add($staff);
        $app->put('/inventory/{id}', [InventoryController::class, 'update'])->add($staff);
        $app->delete('/inventory/{id}', [InventoryController::class, 'delete'])->add($staff);
        $app->post('/inventory/{id}/adjust', [InventoryController::class, 'adjustQuantity'])->add($staff);
    }
}
