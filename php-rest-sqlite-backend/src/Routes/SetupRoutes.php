<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SetupController;
use Slim\App;

class SetupRoutes
{
    public static function register(App $app): void
    {
        $app->get('/setup/check', [SetupController::class, 'checkDatabase']);
        $app->post('/setup/admin', [SetupController::class, 'createAdmin']);
        $app->post('/setup/import/users', [SetupController::class, 'importUsers']);
        $app->post('/setup/import/stores', [SetupController::class, 'importStores']);
        $app->post('/setup/import/products', [SetupController::class, 'importProducts']);
        $app->post('/setup/import/orders', [SetupController::class, 'importOrders']);
        $app->post('/setup/import/coupons', [SetupController::class, 'importCoupons']);
        $app->post('/setup/import/reviews', [SetupController::class, 'importReviews']);
        $app->post('/setup/import/testimonials', [SetupController::class, 'importTestimonials']);
        $app->post('/setup/migrate', [SetupController::class, 'runMigrations']);
    }
}