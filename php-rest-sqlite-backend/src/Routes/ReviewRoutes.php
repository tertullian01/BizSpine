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
        $staff = RouteSecurity::requireStaff();

        $app->get('/reviews', [ReviewController::class, 'getAll']);
        $app->get('/reviews/product/{id}', [ReviewController::class, 'getByProduct']);

        $app->group('/reviews', function ($group) {
            $group->get('/my', [ReviewController::class, 'getMyReviews']);
            $group->get('/{id}', [ReviewController::class, 'getById']);
            $group->post('', [ReviewController::class, 'create']);
            $group->put('/{id}', [ReviewController::class, 'update']);
            $group->delete('/{id}', [ReviewController::class, 'delete']);
        })->add(AuthMiddleware::class);

        $app->post('/reviews/{id}/publish', [ReviewController::class, 'publish'])->add($staff);
        $app->post('/reviews/{id}/unpublish', [ReviewController::class, 'unpublish'])->add($staff);
    }
}
