<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\TestimonialController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class TestimonialRoutes
{
    public static function register(App $app): void
    {
        $app->get('/testimonials', [TestimonialController::class, 'getAll']);
        $app->post('/testimonials', [TestimonialController::class, 'create']);

        $app->group('/testimonials', function ($group) {
            $group->get('/admin', [TestimonialController::class, 'getAllAdmin']);
            $group->get('/{id}', [TestimonialController::class, 'getById']);
            $group->put('/{id}', [TestimonialController::class, 'update']);
            $group->delete('/{id}', [TestimonialController::class, 'delete']);
            $group->post('/{id}/publish', [TestimonialController::class, 'publish']);
            $group->post('/{id}/unpublish', [TestimonialController::class, 'unpublish']);
        })->add(AuthMiddleware::class);
    }
}
