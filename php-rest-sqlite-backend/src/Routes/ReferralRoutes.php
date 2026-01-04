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
        // Public route
        $app->get('/referrals/code/{code}', [ReferralController::class, 'getByCode']);

        $app->group('/referrals', function ($group) {
            $group->get('', [ReferralController::class, 'getAll']);
            $group->post('', [ReferralController::class, 'create']);
            $group->get('/my', [ReferralController::class, 'getMyReferral']);
            $group->get('/my/usage', [ReferralController::class, 'getMyReferralUsage']);
            $group->post('/redeem', [ReferralController::class, 'redeemPoints']);
            $group->get('/{id}', [ReferralController::class, 'getById']);
            $group->put('/{id}', [ReferralController::class, 'update']);
            $group->delete('/{id}', [ReferralController::class, 'delete']);
        })->add(AuthMiddleware::class);
    }
}
