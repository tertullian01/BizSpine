<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SystemController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class SystemRoutes
{
    /** Always-available system endpoints (auth required). */
    public static function register(App $app): void
    {
        $app->get('/system/export', [SystemController::class, 'exportDatabase'])
            ->add(AuthMiddleware::class);
    }

    /** Dangerous maintenance endpoints — only when ALLOW_INSECURE_SETUP is enabled. */
    public static function registerDangerous(App $app): void
    {
        $app->get('/system/import', [SystemController::class, 'importData']);
        $app->get('/system/migrate', [SystemController::class, 'runMigrations']);
    }
}
