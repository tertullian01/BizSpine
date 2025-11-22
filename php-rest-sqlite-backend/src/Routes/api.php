<?php
declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\StoreController;
use App\Controllers\InventoryController;
use App\Controllers\OrderController;
use App\Controllers\ReviewController;
use App\Controllers\TestimonialController;
use App\Controllers\BookkeepingController;
use App\Controllers\ReferralController;
use App\Controllers\CouponController;
use App\Controllers\TaxController;
use App\Controllers\ReturnController;
use App\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/** @var \Slim\App $app */

$config = require __DIR__ . '/../../protected/config/config.php';
$authMiddleware = new AuthMiddleware($config['jwt']['secret'] ?? 'dev');

// Public routes
$app->get('/health', [HealthController::class, 'index']);
$app->post('/auth/register', [AuthController::class, 'register']);
$app->post('/auth/login', [AuthController::class, 'login']);

$app->get('/products', [ProductController::class, 'getAll']);
$app->get('/products/{id}', [ProductController::class, 'getById']);

$app->get('/stores', [StoreController::class, 'getAll']);
$app->get('/stores/{id}', [StoreController::class, 'getById']);

$app->get('/inventory', [InventoryController::class, 'getAll']);
$app->get('/inventory/low-stock', [InventoryController::class, 'getLowStock']);
$app->get('/inventory/{id}', [InventoryController::class, 'getById']);
$app->get('/inventory/product/{id}', [InventoryController::class, 'getByProduct']);
$app->get('/inventory/store/{id}', [InventoryController::class, 'getByStore']);

$app->get('/reviews', [ReviewController::class, 'getAll']);
$app->get('/reviews/product/{id}', [ReviewController::class, 'getByProduct']);

$app->get('/testimonials', [TestimonialController::class, 'getAll']);
$app->post('/testimonials', [TestimonialController::class, 'create']);

// Protected routes (require valid JWT)
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/auth/logout', [AuthController::class, 'logout']);
    $group->post('/auth/refresh', [AuthController::class, 'refresh']);

    $group->post('/products', [ProductController::class, 'create']);
    $group->put('/products/{id}', [ProductController::class, 'update']);
    $group->delete('/products/{id}', [ProductController::class, 'delete']);

    $group->post('/stores', [StoreController::class, 'create']);
    $group->put('/stores/{id}', [StoreController::class, 'update']);
    $group->delete('/stores/{id}', [StoreController::class, 'delete']);

    $group->post('/inventory', [InventoryController::class, 'create']);
    $group->put('/inventory/{id}', [InventoryController::class, 'update']);
    $group->delete('/inventory/{id}', [InventoryController::class, 'delete']);
    $group->post('/inventory/{id}/adjust', [InventoryController::class, 'adjustQuantity']);

    $group->get('/orders', [OrderController::class, 'getAll']);
    $group->get('/orders/my', [OrderController::class, 'getMyOrders']);
    $group->get('/orders/{id}', [OrderController::class, 'getById']);
    $group->post('/orders', [OrderController::class, 'create']);
    $group->put('/orders/{id}', [OrderController::class, 'update']);
    $group->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    $group->post('/orders/{id}/payment', [OrderController::class, 'addPayment']);

    $group->get('/reviews/my', [ReviewController::class, 'getMyReviews']);
    $group->get('/reviews/{id}', [ReviewController::class, 'getById']);
    $group->post('/reviews', [ReviewController::class, 'create']);
    $group->put('/reviews/{id}', [ReviewController::class, 'update']);
    $group->delete('/reviews/{id}', [ReviewController::class, 'delete']);
    $group->post('/reviews/{id}/publish', [ReviewController::class, 'publish']);
    $group->post('/reviews/{id}/unpublish', [ReviewController::class, 'unpublish']);

    $group->get('/testimonials/admin', [TestimonialController::class, 'getAllAdmin']);
    $group->get('/testimonials/{id}', [TestimonialController::class, 'getById']);
    $group->put('/testimonials/{id}', [TestimonialController::class, 'update']);
    $group->delete('/testimonials/{id}', [TestimonialController::class, 'delete']);
    $group->post('/testimonials/{id}/publish', [TestimonialController::class, 'publish']);
    $group->post('/testimonials/{id}/unpublish', [TestimonialController::class, 'unpublish']);

    $group->get('/bookkeeping/income', [BookkeepingController::class, 'getAllIncome']);
    $group->get('/bookkeeping/income/{id}', [BookkeepingController::class, 'getIncomeById']);
    $group->post('/bookkeeping/income', [BookkeepingController::class, 'createIncome']);
    $group->delete('/bookkeeping/income/{id}', [BookkeepingController::class, 'deleteIncome']);

    $group->get('/bookkeeping/expenses', [BookkeepingController::class, 'getAllExpenses']);
    $group->get('/bookkeeping/expenses/{id}', [BookkeepingController::class, 'getExpenseById']);
    $group->post('/bookkeeping/expenses', [BookkeepingController::class, 'createExpense']);
    $group->put('/bookkeeping/expenses/{id}', [BookkeepingController::class, 'updateExpense']);
    $group->delete('/bookkeeping/expenses/{id}', [BookkeepingController::class, 'deleteExpense']);
    $group->get('/bookkeeping/summary', [BookkeepingController::class, 'getSummary']);

    $group->get('/referrals/my', [ReferralController::class, 'getMyReferral']);
    $group->get('/referrals/my/usage', [ReferralController::class, 'getMyReferralUsage']);
    $group->post('/referrals/redeem', [ReferralController::class, 'redeemPoints']);

    $group->get('/coupons', [CouponController::class, 'getAll']);
    $group->get('/coupons/{id}', [CouponController::class, 'getById']);
    $group->post('/coupons', [CouponController::class, 'create']);
    $group->delete('/coupons/{id}', [CouponController::class, 'delete']);
    $group->get('/coupons/usage-report', [CouponController::class, 'getUsageReport']);

    $group->get('/tax/rates', [TaxController::class, 'getAll']);
    $group->get('/tax/default', [TaxController::class, 'getDefault']);
    $group->post('/tax/rates', [TaxController::class, 'create']);

    $group->get('/returns', [ReturnController::class, 'getAll']);
    $group->get('/returns/{id}', [ReturnController::class, 'getById']);
    $group->post('/returns', [ReturnController::class, 'create']);
    $group->post('/returns/{id}/approve', [ReturnController::class, 'approve']);
    $group->post('/returns/{id}/refund', [ReturnController::class, 'processRefund']);

})->add($authMiddleware);
