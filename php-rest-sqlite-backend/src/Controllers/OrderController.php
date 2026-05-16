<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Coupon;
use App\Models\UserReferral;
use App\Services\Database;
use App\Services\EmailService;
use App\Services\Config;
use App\Services\PaginationService;
use App\Services\Logger;
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
    private ?EmailService $emailService;

    public function __construct(?PDO $db = null, ?PaginationService $paginationService = null, ?EmailService $emailService = null)
    {
        $config = Config::getInstance()->getAll();

        if ($db) {
            $this->db = $db;
        } else {
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->validator = new Validator();
        $this->paginationService = $paginationService ?? new PaginationService();
        $this->emailService = $emailService ?? new EmailService($this->db, new Logger(), $config);
    }

    private function jwtRole(Request $request): string
    {
        $token = $request->getAttribute('token');
        if ($token && isset($token->role)) {
            return (string) $token->role;
        }
        return 'customer';
    }

    private function isStaff(Request $request): bool
    {
        return in_array($this->jwtRole($request), ['admin', 'employee'], true);
    }

    private function isOrderOwner(Request $request, object $order): bool
    {
        $uid = $request->getAttribute('user_id');
        if (!$uid || !isset($order->user_id) || $order->user_id === null) {
            return false;
        }

        return (string) $order->user_id === (string) $uid;
    }

    private function canViewOrder(Request $request, object $order): bool
    {
        return $this->isStaff($request) || $this->isOrderOwner($request, $order);
    }

    public function getAll(Request $request, Response $response): Response
    {
        if (!$this->isStaff($request)) {
            return $this->error($response, 'Forbidden', 403);
        }
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
    COALESCE(u.email, o.customer_email) as user_email,
    COALESCE(u.display_name, o.customer_name) as customer_name_display
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
ORDER BY o.order_date DESC
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($orders as $order) {
            $order->items = $this->getOrderItems($order->id);
            $order->payments = $this->getOrderPayments($order->id);
            $order->shipping_carrier = $order->shipping_carrier ?? null;
            $order->tracking_number = $order->tracking_number ?? null;
            $order->tracking_url = $order->tracking_url ?? null;
        }

        $result = $this->paginationService->formatPaginatedResponse($orders, $total, $page, $limit);

        return $this->success($response, $result);
    }

    public function getMyOrders(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
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
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($orders as $order) {
            $order->items = $this->getOrderItems($order->id);
            $order->payments = $this->getOrderPayments($order->id);
            $order->shipping_carrier = $order->shipping_carrier ?? null;
            $order->tracking_number = $order->tracking_number ?? null;
            $order->tracking_url = $order->tracking_url ?? null;
        }

        return $this->success($response, $orders);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $order = $this->loadOrderDetails($id);
        if (!$order) {
            return $this->error($response, 'Order not found', 404);
        }

        if (!$this->canViewOrder($request, $order)) {
            return $this->error($response, 'Forbidden', 403);
        }

        return $this->success($response, $order);
    }

    /** Load order with line items and payments (no access control). */
    private function loadOrderDetails(int $id): ?object
    {
        $sql = <<<'SQL'
SELECT 
    o.*,
    COALESCE(u.email, o.customer_email) as user_email,
    COALESCE(u.display_name, o.customer_name) as customer_name_display
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE o.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetchObject();
        if (!$order) {
            return null;
        }

        $order->items = $this->getOrderItems($order->id);
        $order->payments = $this->getOrderPayments($order->id);
        $order->shipping_carrier = $order->shipping_carrier ?? null;
        $order->tracking_number = $order->tracking_number ?? null;
        $order->tracking_url = $order->tracking_url ?? null;

        return $order;
    }

    private function getOrderByIdForResponder(Request $request, Response $response, int $orderId): Response
    {
        $order = $this->loadOrderDetails($orderId);
        if (!$order) {
            return $this->error($response, 'Order not found', 404);
        }

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
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function getOrderPayments(int $orderId): array
    {
        $sql = 'SELECT * FROM income WHERE order_id = :order_id ORDER BY payment_date DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        
        // For guest checkout, require customer details
        if (!$userId && (empty($body['customer_email']) || empty($body['customer_name']))) {
            return $this->error($response, 'Customer email and name are required for guest checkout', 400);
        } 

        $sendEmail = true;
        if (isset($body['sendEmail'])) {
            $sendEmail = filter_var($body['sendEmail'], FILTER_VALIDATE_BOOLEAN);
        }

        // Custom validation for order creation
        // Shipping address is required only if a shipping method is specified and it is NOT a pickup order.
        // If shipping_method is omitted (e.g. manual entry/POS), address is optional.
        $isShipping = !empty($body['shipping_method']) && stripos($body['shipping_method'], 'pickup') === false;
        if ($isShipping && (!isset($body['shipping_address']) || empty(trim($body['shipping_address'])))) {
            return $this->error($response, 'Shipping Address is required for shipping orders', 400);
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
            $inventoryTracker = []; // Track reserved inventory within this request

            foreach ($body['items'] as $item) {
                $productId = (int) $item['product_id'];
                $storeId = isset($item['store_id']) ? (int) $item['store_id'] : (int) $body['store_id'];
                $quantity = (int) $item['quantity'];
                $stmt = $this->db->prepare('SELECT cost, name, size FROM products WHERE id = :id');
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

                $stmt = $this->db->prepare('SELECT i.quantity, i.price_override, s.name as store_name FROM inventory i JOIN stores s ON i.store_id = s.id WHERE i.product_id = :product_id AND i.store_id = :store_id');
                $stmt->execute([':product_id' => $productId, ':store_id' => $storeId]);
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$inventory) {
                    $unavailableItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'requested_quantity' => $quantity,
                        'available_quantity' => 0,
                        'reason' => "Product '{$product['name']}' (ID: $productId) is not available at Store ID $storeId"
                    ];
                    continue;
                }

                $trackerKey = "p{$productId}_s{$storeId}";
                $reservedQuantity = $inventoryTracker[$trackerKey] ?? 0;
                $availableQuantity = (int)$inventory['quantity'] - $reservedQuantity;

                if ($availableQuantity < $quantity) {
                    $unavailableItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'requested_quantity' => $quantity,
                        'available_quantity' => $availableQuantity,
                        'reason' => "Insufficient inventory for product '{$product['name']}' (ID: $productId) at {$inventory['store_name']}. Available: {$availableQuantity}, Requested: $quantity"
                    ];
                    continue;
                }

                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $product['cost'];
                if (!isset($item['unit_price']) && $inventory['price_override'] !== null) {
                    $unitPrice = (float) $inventory['price_override'];
                }
                $itemSubtotal = $unitPrice * $quantity;

                $inventoryTracker[$trackerKey] = $reservedQuantity + $quantity;
                $subtotal += $itemSubtotal;
                $validatedItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'product_size' => $product['size'],
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
                    'error' => 'Insufficient inventory',
                    'unavailable_items' => $unavailableItems
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $hasCoupon = !empty($body['coupon_code']);
            $hasReferral = !empty($body['referral_code']);
            if ($hasCoupon && $hasReferral) {
                throw new \Exception('Cannot use both coupon code and referral code on the same order');
            }

            $discountAmount = 0;
            $couponCode = null;
            $referralModel = null;
            $couponModel = null;

            if ($hasCoupon) {
                $couponModel = Coupon::fetchOne('SELECT * FROM coupons WHERE UPPER(code) = :code', [':code' => strtoupper($body['coupon_code'])]);
                if (!$couponModel) {
                    throw new \Exception('Invalid coupon code');
                }
                $couponResult = $couponModel->validate($subtotal, $userId);
                if (!$couponResult['valid']) {
                    throw new \Exception($couponResult['error']);
                }

                $discountAmount = $couponResult['discount_amount'];
                $couponCode = $body['coupon_code'];
            } elseif ($hasReferral) {
                if (!$userId) {
                    throw new \Exception('You must be logged in to use a referral code');
                }

                $referralModel = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE referral_code = :code AND status = "active"', [':code' => $body['referral_code']]);

                if (!$referralModel) {
                    throw new \Exception('Invalid or inactive referral code');
                }

                $referralModel->validate($userId);

                // Calculate discount
                if (($referralModel->discount_type ?? 'percentage') === 'percentage') {
                    $discountAmount = $subtotal * (($referralModel->discount_amount ?? 10) / 100);
                } else {
                    $discountAmount = (float) ($referralModel->discount_amount ?? 10);
                }
            } elseif (isset($body['discount_amount']) && empty($body['points_to_redeem'])) {
                $discountAmount = (float) $body['discount_amount'];
            }

            // Handle points redemption
            $pointsToRedeem = 0;
            $pointsDiscount = 0.0;
            $userReferralAccount = null;

            if (isset($body['points_to_redeem']) && (int)$body['points_to_redeem'] > 0) {
                if (!$userId) {
                    throw new \Exception('You must be logged in to redeem points');
                }
                $pointsToRedeem = (int)$body['points_to_redeem'];
                
                $userReferralAccount = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE user_id = :user_id', [':user_id' => $userId]);
                
                if (!$userReferralAccount) {
                    throw new \Exception('Referral account not found');
                }
                if ($userReferralAccount->points_balance < $pointsToRedeem) {
                    throw new \Exception('Insufficient points balance');
                }

                // Conversion rate: 1 point = $0.05 (100 points = $5)
                $pointsDiscount = $pointsToRedeem * 0.05;
                $discountAmount += $pointsDiscount;
            }

            if ($discountAmount > $subtotal) {
                $discountAmount = $subtotal;
            }

            $shippingCost = isset($body['shipping_cost']) ? (float) $body['shipping_cost'] : (isset($body['shipping']) ? (float) $body['shipping'] : 0);
            require_once __DIR__ . '/TaxController.php';
            $taxController = new TaxController();
            $taxableAmount = $subtotal - $discountAmount + $shippingCost;
            $taxCalc = $taxController->calculateTax($taxableAmount, $body['tax_region'] ?? null);
            $taxRate = $taxCalc['tax_rate'];
            $taxAmount = $taxCalc['tax_amount'];
            $total = $taxCalc['total_with_tax'];

            $orderStoreId = isset($body['store_id']) ? (int)$body['store_id'] : ($validatedItems[0]['store_id'] ?? null);

            $fulfillmentStatus = $body['fulfillment_status'] ?? 'pending';
            if (!isset($body['fulfillment_status']) && (!empty($body['payment_method']) || !empty($body['payments']))) {
                $fulfillmentStatus = 'processing';
            }

            $sql = <<<'SQL'
INSERT INTO orders
    (user_id, customer_email, customer_name, store_id, order_number, shipping_address, city, state, postal_code, country, phone_number, whatsapp_number,
     subtotal, discount_amount, coupon_code, shipping_cost, tax_rate, tax_amount, total, notes,
     shipping_method, shipping_carrier, fulfillment_status,
     order_date, created_at, updated_at)
VALUES
    (:user_id, :customer_email, :customer_name, :store_id, :order_number, :shipping_address, :city, :state, :postal_code, :country, :phone_number, :whatsapp_number,
     :subtotal, :discount_amount, :coupon_code, :shipping_cost, :tax_rate, :tax_amount, :total, :notes,
     :shipping_method, :shipping_carrier, :fulfillment_status,
     :order_date, datetime("now"), datetime("now"))
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':customer_email' => $body['customer_email'] ?? null,
                ':customer_name' => $body['customer_name'] ?? null,
                ':store_id' => $orderStoreId,
                ':order_number' => $orderNumber,
                ':shipping_address' => $body['shipping_address'] ?? null,
                ':city' => $body['city'] ?? null,
                ':state' => $body['state'] ?? null,
                ':postal_code' => $body['postal_code'] ?? null,
                ':country' => $body['country'] ?? null,
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
                ':shipping_method' => $body['shipping_method'] ?? null,
                ':shipping_carrier' => $body['shipping_carrier'] ?? null,
                ':fulfillment_status' => $fulfillmentStatus,
                ':order_date' => $body['order_date'] ?? date('Y-m-d H:i:s'),
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

            if ($hasCoupon && $couponModel) {
                $couponModel->recordUsage($userId, $orderId, $discountAmount);
            }

            if ($hasReferral && $userId && $referralModel) {
                $referralModel->recordUsage($userId, $orderId);
            }

            if ($userReferralAccount && $pointsToRedeem > 0) {
                $userReferralAccount->redeemPoints($pointsToRedeem, "Redeemed on Order #{$orderNumber}", $orderId);
            }

            // Handle payments (single or multiple)
            $payments = $body['payments'] ?? [];
            if (empty($payments) && !empty($body['payment_method'])) {
                $payments[] = [
                    'payment_method' => $body['payment_method'],
                    'amount' => $body['payment_amount'] ?? $total,
                    'transaction_id' => $body['transaction_id'] ?? null,
                    'payment_date' => date('Y-m-d H:i:s'),
                    'notes' => $body['payment_notes'] ?? null
                ];
            }

            if (!empty($payments)) {
                foreach ($payments as $payment) {
                    $incomeSql = <<<'SQL'
INSERT INTO income
    (order_id, amount, payment_method, transaction_id, category, payment_date, description, notes, created_at, updated_at)
VALUES
    (:order_id, :amount, :payment_method, :transaction_id, 'Payment', :payment_date, :description, :notes, datetime("now"), datetime("now"))
SQL;
                    $incomeStmt = $this->db->prepare($incomeSql);
                    $incomeStmt->execute([
                        ':order_id' => $orderId,
                        ':amount' => (float) ($payment['amount'] ?? 0),
                        ':payment_method' => $payment['payment_method'] ?? 'Unknown',
                        ':transaction_id' => $payment['transaction_id'] ?? null,
                        ':payment_date' => $payment['payment_date'] ?? date('Y-m-d H:i:s'),
                        ':description' => "Payment for order {$orderNumber}",
                        ':notes' => $payment['notes'] ?? null,
                    ]);
                }
            }

            $this->db->commit();

            if ($this->emailService) {
                try {
                    $stmt = $this->db->prepare('SELECT email, display_name, first_name FROM users WHERE id = :id');
                    $user = null;
                    if ($userId) {
                        $stmt->execute([':id' => $userId]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    $customerName = $body['customer_name'] ?? 'Customer';
                    $customerEmail = $body['customer_email'] ?? null;

                    if ($user) {
                        $customerName = !empty($user['first_name']) ? $user['first_name'] : (!empty($user['display_name']) ? $user['display_name'] : 'Customer');
                        $customerEmail = $user['email'];
                    }

                    $itemsTable = '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse;"><thead><tr><th align="left" style="border-bottom: 1px solid #eee;">Item</th><th align="center" style="border-bottom: 1px solid #eee;">Qty</th><th align="right" style="border-bottom: 1px solid #eee;">Price</th></tr></thead><tbody>';
                    $itemsList = "<ul>";
                    foreach ($validatedItems as $item) {
                        $displayName = $item['product_name'];
                        if (!empty($item['product_size'])) {
                            $displayName .= " ({$item['product_size']})";
                        }
                        $formattedPrice = number_format($item['subtotal'], 2);
                        $itemsList .= "<li>{$displayName} (x{$item['quantity']}) - " . $formattedPrice . "</li>";
                        $itemsTable .= '<tr><td style="border-bottom: 1px solid #eee;">' . htmlspecialchars($displayName) . '</td><td align="center" style="border-bottom: 1px solid #eee;">' . $item['quantity'] . '</td><td align="right" style="border-bottom: 1px solid #eee;">' . $formattedPrice . '</td></tr>';
                    }
                    $itemsList .= "</ul>";
                    $itemsTable .= '</tbody></table>';

                    $paymentsTable = '';
                    if (!empty($payments)) {
                        $paymentsTable = '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; margin-top: 10px;"><thead><tr><th align="left" style="border-bottom: 1px solid #eee;">Date</th><th align="left" style="border-bottom: 1px solid #eee;">Method</th><th align="left" style="border-bottom: 1px solid #eee;">Transaction ID</th><th align="right" style="border-bottom: 1px solid #eee;">Amount</th></tr></thead><tbody>';
                        foreach ($payments as $payment) {
                            $pAmount = (float) ($payment['amount'] ?? 0);
                            $pMethod = $payment['payment_method'] ?? 'Unknown';
                            $pTransId = $payment['transaction_id'] ?? 'N/A';
                            $pDate = $payment['payment_date'] ?? date('Y-m-d H:i:s');
                            $paymentsTable .= '<tr><td style="border-bottom: 1px solid #eee;">' . $pDate . '</td><td style="border-bottom: 1px solid #eee;">' . htmlspecialchars($pMethod) . '</td><td style="border-bottom: 1px solid #eee;">' . htmlspecialchars($pTransId) . '</td><td align="right" style="border-bottom: 1px solid #eee;">' . number_format($pAmount, 2) . '</td></tr>';
                        }
                        $paymentsTable .= '</tbody></table>';
                    }

                    $placeholders = [
                        'customer_name' => $customerName,
                        'order_id' => $orderNumber,
                        'order_number' => $orderNumber,
                        'order_date' => date('Y-m-d H:i:s'),
                        'customer_email' => $customerEmail,
                        'customer_phone' => $body['phone_number'] ?? 'N/A',
                        'whatsapp_number' => $body['whatsapp_number'] ?? 'N/A',
                        'items_table' => $itemsTable,
                        'items_list' => $itemsList,
                        'payments_table' => $paymentsTable,
                        'subtotal' => number_format($subtotal, 2),
                        'discount_amount' => number_format($discountAmount, 2),
                        'coupon_code' => $couponCode ?? '',
                        'shipping_cost' => number_format($shippingCost, 2),
                        'tax_amount' => number_format($taxAmount, 2),
                        'total_amount' => number_format($total, 2),
                        'total' => number_format($total, 2),
                        'shipping_method' => $body['shipping_method'] ?? 'Standard',
                        'shipping_carrier' => $body['shipping_carrier'] ?? '',
                        'shipping_address' => $body['shipping_address'] ?? 'N/A',
                        'city' => $body['city'] ?? '',
                        'state' => $body['state'] ?? '',
                        'postal_code' => $body['postal_code'] ?? '',
                        'country' => $body['country'] ?? '',
                        'notes' => $body['notes'] ?? ''
                    ];

                    // Send to Customer
                    if ($sendEmail && $customerEmail) {
                        try {
                            $this->emailService->sendTemplate($customerEmail, 'order_confirmation', $placeholders, $orderStoreId);
                        } catch (\Exception $e) {
                            error_log('Failed to send customer order confirmation: ' . $e->getMessage());
                        }
                    }

                    // Send to Store/Admin
                    if ($sendEmail) {
                        $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'order_confirmation_email'");
                        $storeEmail = $stmt->fetchColumn();

                        if (!$storeEmail) {
                            $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'site_email'");
                            $storeEmail = $stmt->fetchColumn();
                        }

                        if ($storeEmail) {
                            $emails = array_map('trim', explode(',', $storeEmail));
                            foreach ($emails as $email) {
                                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    try {
                                        $this->emailService->sendTemplate($email, 'store_order_confirmation', $placeholders, $orderStoreId);
                                    } catch (\Exception $e) {
                                        error_log('Failed to send store order confirmation to ' . $email . ': ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but do not fail the request
                    error_log('Error preparing order emails: ' . $e->getMessage());
                }
            }

            return $this->getOrderByIdForResponder($request, $response->withStatus(201), (int) $orderId);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = $request->getParsedBody();
        if (!$this->isStaff($request)) {
            return $this->error($response, 'Forbidden', 403);
        }
        try {
            $this->db->beginTransaction();
            $checkStmt = $this->db->prepare('SELECT id, fulfillment_status, shipping_cost, order_number FROM orders WHERE id = :id');
            $checkStmt->execute([':id' => $id]);
            $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                return $this->error($response, 'Order not found', 404);
            }

            $updates = [];
            $params = [':id' => $id];
            $createShippingExpense = false;
            $shippingCost = 0;
            if (isset($body['fulfillment_status'])) {
                $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($body['fulfillment_status'], $validStatuses)) {
                    $this->db->rollBack();
                    return $this->error($response, 'Invalid fulfillment status', 400);
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

            if (isset($body['tracking_url'])) {
                $updates[] = 'tracking_url = :tracking_url';
                $params[':tracking_url'] = $body['tracking_url'];
            }

            if (isset($body['shipping_carrier'])) {
                $updates[] = 'shipping_carrier = :shipping_carrier';
                $params[':shipping_carrier'] = $body['shipping_carrier'];
            }

            if (isset($body['shipping_method'])) {
                $updates[] = 'shipping_method = :shipping_method';
                $params[':shipping_method'] = $body['shipping_method'];
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
                $updates[] = 'total = subtotal - discount_amount + tax_amount + :shipping_cost';
            }

            if (isset($body['notes'])) {
                $updates[] = 'notes = :notes';
                $params[':notes'] = $body['notes'];
            }

            if (isset($body['order_date'])) {
                $updates[] = 'order_date = :order_date';
                $params[':order_date'] = $body['order_date'];
            }

            if (isset($body['customer_email'])) {
                $updates[] = 'customer_email = :customer_email';
                $params[':customer_email'] = $body['customer_email'];
            }

            if (isset($body['customer_name'])) {
                $updates[] = 'customer_name = :customer_name';
                $params[':customer_name'] = $body['customer_name'];
            }

            if (isset($body['shipping_address'])) {
                $updates[] = 'shipping_address = :shipping_address';
                $params[':shipping_address'] = $body['shipping_address'];
            }

            if (isset($body['phone_number'])) {
                $updates[] = 'phone_number = :phone_number';
                $params[':phone_number'] = $body['phone_number'];
            }

            if (isset($body['whatsapp_number'])) {
                $updates[] = 'whatsapp_number = :whatsapp_number';
                $params[':whatsapp_number'] = $body['whatsapp_number'];
            }

            if (isset($body['city'])) {
                $updates[] = 'city = :city';
                $params[':city'] = $body['city'];
            }

            if (isset($body['state'])) {
                $updates[] = 'state = :state';
                $params[':state'] = $body['state'];
            }

            if (isset($body['postal_code'])) {
                $updates[] = 'postal_code = :postal_code';
                $params[':postal_code'] = $body['postal_code'];
            }

            if (isset($body['country'])) {
                $updates[] = 'country = :country';
                $params[':country'] = $body['country'];
            }

            if (isset($body['store_id'])) {
                $updates[] = 'store_id = :store_id';
                $params[':store_id'] = (int) $body['store_id'];
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return $this->error($response, 'No valid fields to update', 400);
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

            if (isset($body['notify_customer']) && $body['notify_customer'] === true) {
                $this->sendOrderUpdateEmail($id);
            }

            return $this->getById($request, $response, ['id' => $id]);
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }

    public function updateFulfillment(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        if (isset($body['carrier']) && !isset($body['shipping_carrier'])) {
            $body['shipping_carrier'] = $body['carrier'];
        }

        $allowed = [
            'fulfillment_status',
            'tracking_number',
            'tracking_url',
            'shipping_carrier',
            'shipping_method',
            'notify_customer'
        ];

        $filtered = array_intersect_key($body, array_flip($allowed));
        $newRequest = $request->withParsedBody($filtered);

        return $this->update($newRequest, $response, $args);
    }

    private function sendOrderUpdateEmail(int $orderId): void
    {
        if (!$this->emailService) {
            return;
        }

        try {
            // Fetch full order details
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) return;

            $customerEmail = $order['customer_email'];
            if (!$customerEmail) {
                // Try to get from user_id
                if ($order['user_id']) {
                    $uStmt = $this->db->prepare('SELECT email FROM users WHERE id = :id');
                    $uStmt->execute([':id' => $order['user_id']]);
                    $customerEmail = $uStmt->fetchColumn();
                }
            }

            if (!$customerEmail) return;

            // Fetch payments
            $payments = $this->getOrderPayments($orderId);
            $paymentsTable = '';
            if (!empty($payments)) {
                $paymentsTable = '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; margin-top: 10px;"><thead><tr><th align="left" style="border-bottom: 1px solid #eee;">Date</th><th align="left" style="border-bottom: 1px solid #eee;">Method</th><th align="left" style="border-bottom: 1px solid #eee;">Transaction ID</th><th align="right" style="border-bottom: 1px solid #eee;">Amount</th></tr></thead><tbody>';
                foreach ($payments as $payment) {
                    $pTransId = $payment->transaction_id ?? 'N/A';
                    $paymentsTable .= '<tr><td style="border-bottom: 1px solid #eee;">' . $payment->payment_date . '</td><td style="border-bottom: 1px solid #eee;">' . htmlspecialchars($payment->payment_method) . '</td><td style="border-bottom: 1px solid #eee;">' . htmlspecialchars($pTransId) . '</td><td align="right" style="border-bottom: 1px solid #eee;">' . number_format($payment->amount, 2) . '</td></tr>';
                }
                $paymentsTable .= '</tbody></table>';
            }

            $placeholders = [
                'customer_name' => $order['customer_name'] ?? 'Customer',
                'order_number' => $order['order_number'],
                'order_date' => $order['order_date'],
                'fulfillment_status' => ucfirst($order['fulfillment_status']),
                'tracking_number' => $order['tracking_number'] ?? 'N/A',
                'tracking_url' => $order['tracking_url'] ?? '#',
                'shipping_carrier' => $order['shipping_carrier'] ?? 'N/A',
                'carrier' => $order['shipping_carrier'] ?? 'N/A',
                'shipping_method' => $order['shipping_method'] ?? 'Standard',
                'payments_table' => $paymentsTable,
                'notes' => $order['notes'] ?? '',
                'shipping_address' => $order['shipping_address'] ?? ''
            ];

            // Determine template based on status
            $template = 'order_update';
            if ($order['fulfillment_status'] === 'shipped') {
                $template = 'order_shipped';
            } elseif ($order['fulfillment_status'] === 'delivered') {
                $template = 'order_delivered';
            } elseif ($order['fulfillment_status'] === 'cancelled') {
                $template = 'order_cancelled';
            }

            // If tracking URL is present but no specific template logic for it, ensure it's passed
            if (!empty($order['tracking_url'])) {
                $placeholders['tracking_link'] = $order['tracking_url'];
            } else {
                $placeholders['tracking_link'] = '#';
            }

            $this->emailService->sendTemplate($customerEmail, $template, $placeholders, (int)$order['store_id']);

        } catch (\Exception $e) {
            error_log('Failed to send order update email: ' . $e->getMessage());
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
            return $this->error($response, $e->getFirstError(), 400);
        }

        if (!$this->isStaff($request)) {
            return $this->error($response, 'Forbidden', 403);
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT id, order_number, total FROM orders WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                return $this->error($response, 'Order not found', 404);
            }

            $paymentDate = $body['payment_date'] ?? date('Y-m-d H:i:s');
            $notes = $body['notes'] ?? null;
            $transactionId = $body['transaction_id'] ?? null;

            $incomeSql = <<<'SQL'
INSERT INTO income
    (order_id, amount, payment_method, transaction_id, category, payment_date, description, notes, created_at, updated_at)
VALUES
    (:order_id, :amount, :payment_method, :transaction_id, 'Payment', :payment_date, :description, :notes, datetime("now"), datetime("now"))
SQL;
            $incomeStmt = $this->db->prepare($incomeSql);
            $incomeStmt->execute([
                ':order_id' => $id,
                ':amount' => (float) $body['amount'],
                ':payment_method' => $body['payment_method'] ?? 'Unknown',
                ':transaction_id' => $transactionId,
                ':payment_date' => $paymentDate,
                ':description' => "Payment for order {$order['order_number']}",
                ':notes' => $notes,
            ]);
            $this->db->commit();
            return $this->success($response, [
                'message' => 'Payment recorded successfully',
                'order_id' => $id,
                'amount' => (float) $body['amount'],
                'income_id' => (int) $this->db->lastInsertId(),
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->error($response, 'Error recording payment: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $stmt = $this->db->prepare('SELECT fulfillment_status, user_id FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return $this->error($response, 'Order not found', 404);
        }

        $orderForAcl = (object) ['user_id' => $order['user_id']];
        if (!$this->isStaff($request) && !$this->isOrderOwner($request, $orderForAcl)) {
            return $this->error($response, 'Forbidden', 403);
        }

        try {
            $this->db->beginTransaction();

            if ($order['fulfillment_status'] === 'cancelled') {
                $this->db->rollBack();
                return $this->error($response, 'Order is already cancelled', 400);
            }

            if (in_array($order['fulfillment_status'], ['shipped', 'delivered'])) {
                $this->db->rollBack();
                return $this->error($response, 'Cannot cancel order that has been shipped or delivered', 400);
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->error($response, 'Error cancelling order: ' . $e->getMessage(), 500);
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->error($response, 'Error deleting order: ' . $e->getMessage(), 500);
        }
    }
}
