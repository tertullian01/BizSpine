<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\SetupController;
use Slim\App;

class SetupRoutes
{
    public static function register(App $app): void
    {
        $app->group('/setup', function ($group) {
            $group->get('/check', [SetupController::class, 'checkDatabase']);
            $group->post('/admin', [SetupController::class, 'createAdmin']);
            $group->post('/migrate', [SetupController::class, 'runMigrations']);
            $group->post('/import/users', [SetupController::class, 'importUsers']);
            $group->post('/import/stores', [SetupController::class, 'importStores']);
            $group->post('/import/products', [SetupController::class, 'importProducts']);
            $group->post('/import/orders', [SetupController::class, 'importOrders']);
            $group->post('/import/coupons', [SetupController::class, 'importCoupons']);
            $group->post('/import/reviews', [SetupController::class, 'importReviews']);
            $group->post('/import/testimonials', [SetupController::class, 'importTestimonials']);
        });
    }
}