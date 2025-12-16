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
        $app->group('/referrals', function ($group) {
            $group->get('', [ReferralController::class, 'getAll']);
            $group->get('/my', [ReferralController::class, 'getMyReferral']);
            $group->get('/my/usage', [ReferralController::class, 'getMyReferralUsage']);
            $group->post('/redeem', [ReferralController::class, 'redeemPoints']);
            $group->get('/{id}', [ReferralController::class, 'getById']);
            $group->put('/{id}', [ReferralController::class, 'update']);
            $group->delete('/{id}', [ReferralController::class, 'delete']);
        })->add(AuthMiddleware::class);
    }
}
