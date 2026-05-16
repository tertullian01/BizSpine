<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\TaxController;
use Slim\App;

class TaxRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();
        $app->group('/tax/rates', function ($group) {
            $group->get('', [TaxController::class, 'getAll']);
            $group->post('', [TaxController::class, 'create']);
            $group->get('/default', [TaxController::class, 'getDefault']);
            $group->get('/region/{region}', [TaxController::class, 'getByRegion']);
            $group->get('/{id:[0-9]+}', [TaxController::class, 'getById']);
            $group->put('/{id:[0-9]+}', [TaxController::class, 'update']);
            $group->delete('/{id:[0-9]+}', [TaxController::class, 'delete']);
        })->add($staff);
    }
}