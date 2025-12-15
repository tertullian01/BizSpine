<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\PaginationService;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class OrderController extends ApiController
{
    private PDO $db;
    private Validator $validator;
    private PaginationService $paginationService;

    public function __construct(?PDO $db = null, ?PaginationService $paginationService = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->validator = new Validator();
        $this->paginationService = $paginationService ?? new PaginationService();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $page = $pagination['page'];
        $limit = $pagination['limit'];
        $offset = $pagination['offset'];

        // Get total count
        $countStmt = $this->db->query('SELECT COUNT(*) as total FROM orders');
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = <<<'SQL'
SELECT
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
ORDER BY o.order_date DESC
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Order');
        foreach ($orders as $order) {
            $order->items = $this->getOrderItems($order->id);
        }

        $result = $this->paginationService->formatPaginatedResponse($orders, $total, $page, $limit);

        return $this->success($response, $result);
    }

    public function getMyOrders(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $sql = <<<'SQL'
SELECT 
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE o.user_id = :user_id
ORDER BY o.order_date DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Order');
        foreach ($orders as $order) {
            $order->items = $this->getOrderItems($order->id);
        }

        return $this->success($response, $orders);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $sql = <<<'SQL'
SELECT 
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE o.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetchObject('App\Models\Order');
        if (!$order) {
            return $this->error($response, 'Order not found', 404);
        }

        $order->items = $this->getOrderItems($order->id);
        return $this->success($response, $order);
    }

    private function getOrderItems(int $orderId): array
    {
        $sql = <<<'SQL'
SELECT 
    oi.*,
    p.name as product_name,
    s.name as store_name
FROM order_items oi
LEFT JOIN products p ON oi.product_id = p.id
LEFT JOIN stores s ON oi.store_id = s.id
WHERE oi.order_id = :order_id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\OrderItem');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        // Custom validation for order creation
        if (!isset($body['shipping_address']) || empty(trim($body['shipping_address']))) {
            return $this->error($response, 'Shipping Address is required', 400);
        }

        if (!isset($body['items']) || !is_array($body['items']) || empty($body['items'])) {
            return $this->error($response, 'Items are required', 400);
        }

        foreach ($body['items'] as $index => $item) {
            if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                return $this->error($response, 'Product ID is required for all items', 400);
            }
            if ((!isset($item['store_id']) || !is_numeric($item['store_id'])) && (!isset($body['store_id']) || !is_numeric($body['store_id']))) {
                return $this->error($response, 'Store ID is required for all items or at the order level', 400);
            }
            if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                return $this->error($response, 'Valid quantity is required for all items', 400);
            }
        }

        try {
            $this->db->beginTransaction();
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $subtotal = 0;
            $validatedItems = [];
            $unavailableItems = [];
            foreach ($body['items'] as $item) {
                $productId = (int) $item['product_id'];
                $storeId = isset($item['store_id']) ? (int) $item['store_id'] : (int) $body['store_id'];
                $quantity = (int) $item['quantity'];
                $stmt = $this->db->prepare('SELECT cost, name FROM products WHERE id = :id');
                $stmt->execute([':id' => $productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    $unavailableItems[] = [
                        'product_id' => $productId,
                        'product_name' => 'Unknown',
                        'requested_quantity' => $quantity,
                        'available_quantity' => 0,
                        'reason' => "Product with ID $productId not found"
                    ];
                    continue;
                }

                $unitPrice = (float) $product['cost'];
                $itemSubtotal = $unitPrice * $quantity;
                $stmt = $this->db->prepare('SELECT quantity FROM inventory WHERE product_id = :product_id AND store_id = :store_id');
                $stmt->execute([':product_id' => $productId, ':store_id' => $storeId]);
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$inventory) {
                    $unavailableItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'requested_quantity' => $quantity,
                        'available_quantity' => 0,
                        'reason' => "Product '{$product['name']}' (ID: $productId) is not available at the selected store"
                    ];
                    continue;
                }

                if ($inventory['quantity'] < $quantity) {
                    $unavailableItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'requested_quantity' => $quantity,
                        'available_quantity' => (int)$inventory['quantity'],
                        'reason' => "Insufficient inventory for product '{$product['name']}' (ID: $productId). Available: {$inventory['quantity']}, Requested: $quantity"
                    ];
                    continue;
                }

                $subtotal += $itemSubtotal;
                $validatedItems[] = [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal
                ];
            }

            if (!empty($unavailableItems)) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Some items are not available',
                    'unavailable_items' => $unavailableItems
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $hasCoupon = !empty($body['coupon_code']);
            $hasReferral = !empty($body['referral_code']);
            if ($hasCoupon && $hasReferral) {
                throw new ValidationException('Cannot use both coupon code and referral code on the same order');
            }

            $discountAmount = 0;
            $couponCode = null;
            if ($hasCoupon) {
                require_once __DIR__ . '/CouponController.php';
                $couponController = new CouponController($this->db);
                $couponResult = $couponController->validateCoupon($body['coupon_code'], $subtotal, (int) $userId, 0);
                if (!$couponResult['valid']) {
                    throw new \Exception($couponResult['error']);
                }

                $discountAmount = $couponResult['discount_amount'];
                $couponCode = $body['coupon_code'];
            } elseif (isset($body['discount_amount'])) {
                $discountAmount = (float) $body['discount_amount'];
            }

            $shippingCost = isset($body['shipping_cost']) ? (float) $body['shipping_cost'] : 0;
            require_once __DIR__ . '/TaxController.php';
            $taxController = new TaxController();
            $taxableAmount = $subtotal - $discountAmount + $shippingCost;
            $taxCalc = $taxController->calculateTax($taxableAmount, $body['tax_region'] ?? null);
            $taxRate = $taxCalc['tax_rate'];
            $taxAmount = $taxCalc['tax_amount'];
            $total = $taxCalc['total_with_tax'];

            $orderStoreId = isset($body['store_id']) ? (int)$body['store_id'] : ($validatedItems[0]['store_id'] ?? null);

            $sql = <<<'SQL'
INSERT INTO orders
    (user_id, store_id, order_number, shipping_address, phone_number, whatsapp_number,
     subtotal, discount_amount, coupon_code, shipping_cost, tax_rate, tax_amount, total, notes,
     order_date, created_at, updated_at)
VALUES
    (:user_id, :store_id, :order_number, :shipping_address, :phone_number, :whatsapp_number,
     :subtotal, :discount_amount, :coupon_code, :shipping_cost, :tax_rate, :tax_amount, :total, :notes,
     datetime("now"), datetime("now"), datetime("now"))
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':store_id' => $orderStoreId,
                ':order_number' => $orderNumber,
                ':shipping_address' => $body['shipping_address'],
                ':phone_number' => $body['phone_number'] ?? null,
                ':whatsapp_number' => $body['whatsapp_number'] ?? null,
                ':subtotal' => $subtotal,
                ':discount_amount' => $discountAmount,
                ':coupon_code' => $couponCode,
                ':shipping_cost' => $shippingCost,
                ':tax_rate' => $taxRate,
                ':tax_amount' => $taxAmount,
                ':total' => $total,
                ':notes' => $body['notes'] ?? null,
            ]);
            $orderId = (int) $this->db->lastInsertId();
            $itemSql = <<<'SQL'
INSERT INTO order_items 
    (order_id, product_id, store_id, quantity, unit_price, subtotal, created_at) 
VALUES 
    (:order_id, :product_id, :store_id, :quantity, :unit_price, :subtotal, datetime("now"))
SQL;
            $itemStmt = $this->db->prepare($itemSql);
            foreach ($validatedItems as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':store_id' => $item['store_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':subtotal' => $item['subtotal'],
                ]);
                $invSql = 'UPDATE inventory SET quantity = quantity - :quantity, updated_at = datetime("now") WHERE product_id = :product_id AND store_id = :store_id';
                $invStmt = $this->db->prepare($invSql);
                $invStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['product_id'],
                    ':store_id' => $item['store_id'],
                ]);
            }

            if ($hasCoupon && isset($couponResult) && $couponResult['valid']) {
                $updateUsageSql = 'UPDATE coupon_usage SET order_id = :order_id WHERE coupon_id = :coupon_id AND user_id = :user_id AND order_id = 0';
                $updateUsageStmt = $this->db->prepare($updateUsageSql);
                $updateUsageStmt->execute([
                    ':order_id' => $orderId,
                    ':coupon_id' => $couponResult['coupon_id'],
                    ':user_id' => $userId,
                ]);
            }

            if ($hasReferral) {
                $orderCountStmt = $this->db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
                $orderCountStmt->execute([':user_id' => $userId]);
                $orderCount = (int) $orderCountStmt->fetchColumn();
                if ($orderCount === 1) {
                    require_once __DIR__ . '/ReferralController.php';
                    $referralController = new ReferralController();
                    $referralController->validateReferralCode($body['referral_code'], (int) $userId);
                }
            }

            $this->db->commit();
            return $this->getById($request, $response->withStatus(201), ['id' => $orderId]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = $request->getParsedBody();
        try {
            $this->db->beginTransaction();
            $checkStmt = $this->db->prepare('SELECT id, fulfillment_status, shipping_cost, order_number FROM orders WHERE id = :id');
            $checkStmt->execute([':id' => $id]);
            $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $updates = [];
            $params = [':id' => $id];
            $createShippingExpense = false;
            $shippingCost = 0;
            if (isset($body['fulfillment_status'])) {
                $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($body['fulfillment_status'], $validStatuses)) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode(['error' => 'Invalid fulfillment status']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                $updates[] = 'fulfillment_status = :fulfillment_status';
                $params[':fulfillment_status'] = $body['fulfillment_status'];
                if ($body['fulfillment_status'] === 'shipped' && $order['fulfillment_status'] !== 'shipped') {
                    $updates[] = 'shipping_date = datetime("now")';
                    if (isset($body['tracking_number']) && $order['shipping_cost'] > 0) {
                        $createShippingExpense = true;
                        $shippingCost = (float) $order['shipping_cost'];
                    }
                }
            }

            if (isset($body['tracking_number'])) {
                $updates[] = 'tracking_number = :tracking_number';
                $params[':tracking_number'] = $body['tracking_number'];
                if (isset($body['fulfillment_status']) && $body['fulfillment_status'] === 'shipped' && $order['shipping_cost'] > 0) {
                    $createShippingExpense = true;
                    $shippingCost = (float) $order['shipping_cost'];
                }
            }

            if (isset($body['shipping_cost'])) {
                $updates[] = 'shipping_cost = :shipping_cost';
                $params[':shipping_cost'] = (float) $body['shipping_cost'];
                $updates[] = 'total = subtotal - discount_amount + :shipping_cost';
            }

            if (isset($body['notes'])) {
                $updates[] = 'notes = :notes';
                $params[':notes'] = $body['notes'];
            }

            if (isset($body['store_id'])) {
                $updates[] = 'store_id = :store_id';
                $params[':store_id'] = (int) $body['store_id'];
            }

            if (empty($updates)) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'No valid fields to update']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $updates[] = 'updated_at = datetime("now")';
            $sql = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            if ($createShippingExpense) {
                $expenseSql = <<<'SQL'
INSERT INTO expenses
    (order_id, vendor, category, amount, expense_date, description, created_at, updated_at)
VALUES
    (:order_id, 'Shipping Provider', 'Shipping', :amount, datetime("now"), :description, datetime("now"), datetime("now"))
SQL;
                $expenseStmt = $this->db->prepare($expenseSql);
                $expenseStmt->execute([
                    ':order_id' => $id,
                    ':amount' => $shippingCost,
                    ':description' => "Shipping cost for order {$order['order_number']}",
                ]);
            }

            $this->db->commit();
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function addPayment(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = $request->getParsedBody();
        try {
            $this->validator->validate($body, [
                'amount' => v::notEmpty()->floatVal()->positive()->setName('Amount'),
            ]);
        } catch (ValidationException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getFirstError()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT id, order_number, total FROM orders WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $incomeSql = <<<'SQL'
INSERT INTO income
    (order_id, amount, payment_method, payment_date, description, notes, created_at, updated_at)
VALUES
    (:order_id, :amount, :payment_method, datetime("now"), :description, :notes, datetime("now"), datetime("now"))
SQL;
            $incomeStmt = $this->db->prepare($incomeSql);
            $incomeStmt->execute([
                ':order_id' => $id,
                ':amount' => (float) $body['amount'],
                ':payment_method' => $body['payment_method'] ?? 'Unknown',
                ':description' => "Payment for order {$order['order_number']}",
                ':notes' => $body['notes'] ?? null,
            ]);
            $this->db->commit();
            $response->getBody()->write(json_encode([
                'message' => 'Payment recorded successfully',
                'order_id' => $id,
                'amount' => (float) $body['amount'],
                'income_id' => (int) $this->db->lastInsertId(),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Error recording payment: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT fulfillment_status FROM orders WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if ($order['fulfillment_status'] === 'cancelled') {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Order is already cancelled']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (in_array($order['fulfillment_status'], ['shipped', 'delivered'])) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Cannot cancel order that has been shipped or delivered']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmt = $this->db->prepare('SELECT product_id, store_id, quantity FROM order_items WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $invSql = 'UPDATE inventory SET quantity = quantity + :quantity, updated_at = datetime("now") WHERE product_id = :product_id AND store_id = :store_id';
            $invStmt = $this->db->prepare($invSql);
            foreach ($items as $item) {
                $invStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['product_id'],
                    ':store_id' => $item['store_id'],
                ]);
            }

            $stmt = $this->db->prepare('UPDATE orders SET fulfillment_status = :status, updated_at = datetime("now") WHERE id = :id');
            $stmt->execute([':status' => 'cancelled', ':id' => $id]);
            $this->db->commit();
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Error cancelling order: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $userId = $request->getAttribute('user_id');

        // Check if user is admin
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $userRole = $stmt->fetchColumn();

        if ($userRole !== 'admin') {
            return $this->error($response, 'Unauthorized. Admin access required.', 403);
        }

        try {
            $this->db->beginTransaction();

            // Check if order exists
            $stmt = $this->db->prepare('SELECT id FROM orders WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if (!$stmt->fetch()) {
                $this->db->rollBack();
                return $this->error($response, 'Order not found', 404);
            }

            // Delete related records
            $this->db->prepare('DELETE FROM order_items WHERE order_id = :id')->execute([':id' => $id]);
            $this->db->prepare('DELETE FROM expenses WHERE order_id = :id')->execute([':id' => $id]);
            $this->db->prepare('DELETE FROM income WHERE order_id = :id')->execute([':id' => $id]);
            $this->db->prepare('DELETE FROM coupon_usage WHERE order_id = :id')->execute([':id' => $id]);

            // Delete the order
            $this->db->prepare('DELETE FROM orders WHERE id = :id')->execute([':id' => $id]);

            $this->db->commit();
            return $response->withStatus(204);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->error($response, 'Error deleting order: ' . $e->getMessage(), 500);
        }
    }
}
