<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ReviewController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class ReviewRoutes
{
    public static function register(App $app): void
    {
        $app->get('/reviews', [ReviewController::class, 'getAll']);
        $app->get('/reviews/product/{id}', [ReviewController::class, 'getByProduct']);

        $app->group('/reviews', function ($group) {
            $group->get('/my', [ReviewController::class, 'getMyReviews']);
            $group->get('/{id}', [ReviewController::class, 'getById']);
            $group->post('', [ReviewController::class, 'create']);
            $group->put('/{id}', [ReviewController::class, 'update']);
            $group->delete('/{id}', [ReviewController::class, 'delete']);
            $group->post('/{id}/publish', [ReviewController::class, 'publish']);
            $group->post('/{id}/unpublish', [ReviewController::class, 'unpublish']);
        })->add(AuthMiddleware::class);
    }
}
