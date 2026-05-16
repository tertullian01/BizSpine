<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\StoreController;
use Slim\App;

class StoreRoutes
{
    public static function register(App $app): void
    {
        $staff = RouteSecurity::requireStaff();

        $app->get('/stores', [StoreController::class, 'getAll']);
        $app->get('/stores/{id}', [StoreController::class, 'getById']);
        $app->post('/stores', [StoreController::class, 'create'])->add($staff);
        $app->put('/stores/{id}', [StoreController::class, 'update'])->add($staff);
        $app->delete('/stores/{id}', [StoreController::class, 'delete'])->add($staff);
        $app->post('/stores/{id}/upload-logo', [StoreController::class, 'uploadLogo'])->add($staff);
    }
}
