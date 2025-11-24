<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SetupController;
use Slim\App;

class SetupRoutes
{
    public static function register(App $app): void
    {
        $app->get('/setup/status', [SetupController::class, 'checkSetupStatus']);
        $app->post('/setup', [SetupController::class, 'performSetup']);
    }
}