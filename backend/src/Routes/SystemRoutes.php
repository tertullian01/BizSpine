<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SystemController;
use Slim\App;

class SystemRoutes
{
    public static function register(App $app): void
    {
        $app->get('/system/import', [SystemController::class, 'importData']);
        $app->get('/system/migrate', [SystemController::class, 'runMigrations']);
    }
}