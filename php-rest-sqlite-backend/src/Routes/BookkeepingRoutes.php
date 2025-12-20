<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\BookkeepingController;
use App\Middleware\AuthMiddleware;
use Slim\App;

class BookkeepingRoutes
{
    public static function register(App $app): void
    {
        $app->group('/bookkeeping', function ($group) {
            $group->get('/income', [BookkeepingController::class, 'getAllIncome']);
            $group->get('/income/{id}', [BookkeepingController::class, 'getIncomeById']);
            $group->post('/income', [BookkeepingController::class, 'createIncome']);
            $group->delete('/income/{id}', [BookkeepingController::class, 'deleteIncome']);
            $group->put('/income/{id}', [BookkeepingController::class, 'updateIncome']);
            $group->get('/expenses', [BookkeepingController::class, 'getAllExpenses']);
            $group->get('/expenses/{id}', [BookkeepingController::class, 'getExpenseById']);
            $group->post('/expenses', [BookkeepingController::class, 'createExpense']);
            $group->put('/expenses/{id}', [BookkeepingController::class, 'updateExpense']);
            $group->delete('/expenses/{id}', [BookkeepingController::class, 'deleteExpense']);
            $group->post('/expenses/{id}/upload-receipt', [BookkeepingController::class, 'uploadReceipt']);
            $group->get('/summary', [BookkeepingController::class, 'getSummary']);
        })->add(AuthMiddleware::class);
    }
}
