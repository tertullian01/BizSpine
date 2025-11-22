<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ReturnController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class ReturnRoutes
{
    public static function register(App $app): void
    {
        $app->group('/returns', function ($group) {
            $group->get('', [ReturnController::class, 'getAll']);
            $group->get('/{id}', [ReturnController::class, 'getById']);
            $group->post('', [ReturnController::class, 'create']);
            $group->post('/{id}/approve', [ReturnController::class, 'approve']);
            $group->post('/{id}/refund', [ReturnController::class, 'processRefund']);
        })->add(AuthMiddleware::class);
    }
}
