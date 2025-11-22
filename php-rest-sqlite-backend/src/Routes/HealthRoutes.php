<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\HealthController;
use Slim\App;

class HealthRoutes
{
    public static function register(App $app): void
    {
        $app->get('/health', [HealthController::class, 'index']);
    }
}
