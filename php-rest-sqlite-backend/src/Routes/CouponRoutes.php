<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\CouponController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class CouponRoutes
{
    public static function register(App $app): void
    {
        $app->group('/coupons', function ($group) {
            $group->get('', [CouponController::class, 'getAll']);
            $group->get('/usage-report', [CouponController::class, 'getUsageReport']);
            $group->get('/{id}', [CouponController::class, 'getById']);
            $group->post('', [CouponController::class, 'create']);
            $group->put('/{id}', [CouponController::class, 'update']);
            $group->delete('/{id}', [CouponController::class, 'delete']);
        })->add(AuthMiddleware::class);
    }
}
