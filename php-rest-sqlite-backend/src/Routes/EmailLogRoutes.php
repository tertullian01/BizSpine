<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\EmailLogController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class EmailLogRoutes
{
    public static function register(App $app): void
    {
        $app->group('/email-logs', function ($group) {
            $group->get('', [EmailLogController::class, 'getAll']);
        })->add(AuthMiddleware::class);
    }
}