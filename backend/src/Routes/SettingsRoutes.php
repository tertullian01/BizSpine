<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class SettingsRoutes
{
    public static function register(App $app): void
    {
        $app->group('/settings', function ($group) {
            $group->get('', [SettingsController::class, 'getAll']);
            $group->put('', [SettingsController::class, 'update']);
            $group->post('/logo', [SettingsController::class, 'uploadLogo']);
            $group->post('/verify-email', [SettingsController::class, 'sendVerificationEmail']);
            $group->post('/verify-email/check', [SettingsController::class, 'verifyEmailCode']);
        })->add(AuthMiddleware::class);
    }
}