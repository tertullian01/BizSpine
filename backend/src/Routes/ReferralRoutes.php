<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ReferralController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class ReferralRoutes
{
    public static function register(App $app): void
    {
        $app->get('/referrals/code/{code}', [ReferralController::class, 'getByCode']);

        $app->group('/referrals', function ($group) {
            $group->get('/my', [ReferralController::class, 'getMyReferral']);
            $group->get('/my/usage', [ReferralController::class, 'getMyReferralUsage']);
            $group->post('/redeem', [ReferralController::class, 'redeemPoints']);
        })->add(AuthMiddleware::class);

        $admin = RouteSecurity::requireAdmin();
        $app->group('/referrals', function ($group) {
            $group->get('', [ReferralController::class, 'getAll']);
            $group->post('', [ReferralController::class, 'create']);
            $group->get('/{id}/usage', [ReferralController::class, 'getUsageById']);
            $group->get('/{id}/log', [ReferralController::class, 'getReferralLog']);
            $group->post('/usage', [ReferralController::class, 'addUsage']);
            $group->post('/redemption', [ReferralController::class, 'manualRedemption']);
            $group->get('/{id}', [ReferralController::class, 'getById']);
            $group->put('/{id}', [ReferralController::class, 'update']);
            $group->delete('/{id}', [ReferralController::class, 'delete']);
        })->add($admin);
    }
}
