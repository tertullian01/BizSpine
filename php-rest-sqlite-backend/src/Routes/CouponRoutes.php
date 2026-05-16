<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\CouponController;
use Slim\App;

class CouponRoutes
{
    public static function register(App $app): void
    {
        // Public route for code lookup
        $app->get('/coupons/code/{code}', [CouponController::class, 'getByCode']);

        $admin = RouteSecurity::requireAdmin();
        $app->group('/coupons', function ($group) {
            $group->get('', [CouponController::class, 'getAll']);
            $group->get('/usage-report', [CouponController::class, 'getUsageReport']);
            $group->get('/{id:[0-9]+}', [CouponController::class, 'getById']);
            $group->post('', [CouponController::class, 'create']);
            $group->put('/{id:[0-9]+}', [CouponController::class, 'update']);
            $group->delete('/{id:[0-9]+}', [CouponController::class, 'delete']);
        })->add($admin);

        // Public fallback for direct code lookup (e.g. /coupons/GroupBuy)
        $app->get('/coupons/{code}', [CouponController::class, 'getByCode']);
    }
}
