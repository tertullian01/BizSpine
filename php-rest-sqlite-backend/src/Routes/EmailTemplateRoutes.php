<?php

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\EmailTemplateController;
use App\Middleware\AuthMiddleware;

$app->group('/email-templates', function (RouteCollectorProxy $group) {
    $group->get('', [EmailTemplateController::class, 'getAll']);
    $group->post('', [EmailTemplateController::class, 'create']);
    $group->get('/{id}', [EmailTemplateController::class, 'getById']);
    $group->put('/{id}', [EmailTemplateController::class, 'update']);
    $group->delete('/{id}', [EmailTemplateController::class, 'delete']);
})->add(AuthMiddleware::class);