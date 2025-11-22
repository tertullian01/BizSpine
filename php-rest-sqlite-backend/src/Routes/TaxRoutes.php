<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\TaxController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class TaxRoutes
{
    public static function register(App $app): void
    {
        $app->get('/tax/rates', [TaxController::class, 'getAll'])->add(AuthMiddleware::class);
        $app->get('/tax/default', [TaxController::class, 'getDefault'])->add(AuthMiddleware::class);
        $app->post('/tax/rates', [TaxController::class, 'create'])->add(AuthMiddleware::class);
    }
}
