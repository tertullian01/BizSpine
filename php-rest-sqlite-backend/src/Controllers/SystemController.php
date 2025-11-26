<?php

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class SystemController extends ApiController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function importData(Request $request, Response $response): Response
    {
        $output = "<h1>Data Import Log</h1>";

        try {
            $this->db->exec('PRAGMA foreign_keys = ON;');

            // 1. Import Coupons
            $output .= "<h2>Importing Coupons...</h2>";
            $coupons = $this->readJson('coupon.json');
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO coupons (code, discount_type, discount_value, description, is_active) VALUES (:code, :type, :value, :desc, 1)');

            foreach ($coupons as $code => $data) {
                $stmt->execute([
                    ':code' => $code,
                    ':type' => 'percentage',
                    ':value' => $data['discount'],
                    ':desc' => $data['description']
                ]);
                $output .= "Processed coupon: $code<br>";
            }

            // 2. Import Orders, Clients, Products
            $output .= "<h2>Importing Orders, Clients, and Products...</h2>";
            $orders = $this->readJson('orders.json');

            // Get default store (USA)
            $stmt = $this->db->prepare("SELECT id FROM stores WHERE name = 'USA'");
            $stmt->execute();
            $storeId = $stmt->fetchColumn();
            if (!$storeId) {
                $this->db->exec("INSERT INTO stores (name, description) VALUES ('USA', 'USA Store')");
                $storeId = $this->db->lastInsertId();
            }

            foreach ($orders as $orderData) {
                $this->db->beginTransaction();

                try {
                    // 2a. Client (User)
                    $email = $orderData['customer_info']['email'];
                    $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
                    $stmt->execute([':email' => $email]);
                    $userId = $stmt->fetchColumn();

                    if (!$userId) {
                        $stmt = $this->db->prepare('INSERT INTO users (email, display_name, created_at) VALUES (:email, :name, :created_at)');
                        $stmt->execute([
                            ':email' => $email,
                            ':name' => $orderData['customer_info']['name'],
                            ':created_at' => $orderData['timestamp']
                        ]);
                        $userId = $this->db->lastInsertId();
                        $output .= "Created user: $email<br>";
                    }

                    // 2b. Order
                    $stmt = $this->db->prepare('SELECT id FROM orders WHERE order_number = :ord_num');
                    $stmt->execute([':ord_num' => $orderData['order_number']]);
                    if ($stmt->fetch()) {
                        $output .= "Order {$orderData['order_number']} already exists. Skipping.<br>";
                        $this->db->rollBack();
                        continue;
                    }

                    $shippingAddress = isset($orderData['shipping_address']) ? json_encode($orderData['shipping_address']) : '';
                    $phone = $orderData['customer_info']['phone'] ?? null;
                    $whatsapp = $orderData['customer_info']['whatsapp'] ?? null;

                    $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO orders (
    user_id, order_number, order_date, fulfillment_status, shipping_address, 
    phone_number, whatsapp_number, subtotal, shipping_cost, total, created_at
) VALUES (
    :user_id, :order_number, :order_date, :status, :address, 
    :phone, :whatsapp, :subtotal, :shipping, :total, :created_at
)
SQL
                    );

                    $stmt->execute([
                        ':user_id' => $userId,
                        ':order_number' => $orderData['order_number'],
                        ':order_date' => $orderData['timestamp'],
                        ':status' => 'pending',
                        ':address' => $shippingAddress,
                        ':phone' => $phone,
                        ':whatsapp' => $whatsapp,
                        ':subtotal' => (float) $orderData['totals']['subtotal'],
                        ':shipping' => (float) $orderData['totals']['shipping'],
                        ':total' => (float) $orderData['totals']['total'],
                        ':created_at' => $orderData['timestamp']
                    ]);
                    $orderId = $this->db->lastInsertId();

                    // 2c. Order Items & Products
                    foreach ($orderData['cart_items'] as $item) {
                        $stmt = $this->db->prepare('SELECT id FROM products WHERE name = :name');
                        $stmt->execute([':name' => $item['name']]);
                        $productId = $stmt->fetchColumn();

                        if (!$productId) {
                            $stmt = $this->db->prepare('INSERT INTO products (name, size, cost) VALUES (:name, :size, :cost)');
                            $stmt->execute([
                                ':name' => $item['name'],
                                ':size' => $item['size'],
                                ':cost' => (float) $item['price']
                            ]);
                            $productId = $this->db->lastInsertId();
                            $output .= "Created product: {$item['name']}<br>";
                        }

                        $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal)
VALUES (:order_id, :product_id, :store_id, :quantity, :price, :subtotal)
SQL
                        );
                        $stmt->execute([
                            ':order_id' => $orderId,
                            ':product_id' => $productId,
                            ':store_id' => $storeId,
                            ':quantity' => (int) $item['quantity'],
                            ':price' => (float) $item['price'],
                            ':subtotal' => (float) $item['price'] * (int) $item['quantity']
                        ]);
                    }

                    $this->db->commit();
                    $output .= "Imported order: {$orderData['order_number']}<br>";

                } catch (Exception $e) {
                    $this->db->rollBack();
                    $output .= "Error processing order {$orderData['order_number']}: " . $e->getMessage() . "<br>";
                }
            }

            // 3. Import Reviews
            $output .= "<h2>Importing Reviews...</h2>";
            $reviews = $this->readJson('reviews.json');

            $stmt = $this->db->query("SELECT id FROM products LIMIT 1");
            $fallbackProductId = $stmt->fetchColumn();

            foreach ($reviews as $review) {
                $stmt = $this->db->prepare('SELECT id FROM users WHERE display_name = :name');
                $stmt->execute([':name' => $review['name']]);
                $userId = $stmt->fetchColumn();

                if (!$userId) {
                    $email = strtolower(str_replace(' ', '.', $review['name'])) . '@example.com';
                    $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
                    $stmt->execute([':email' => $email]);
                    if ($stmt->fetch()) {
                        $email = strtolower(str_replace(' ', '.', $review['name'])) . uniqid() . '@example.com';
                    }

                    $stmt = $this->db->prepare('INSERT INTO users (email, display_name, created_at) VALUES (:email, :name, :created_at)');
                    $stmt->execute([
                        ':email' => $email,
                        ':name' => $review['name'],
                        ':created_at' => $review['timestamp']
                    ]);
                    $userId = $this->db->lastInsertId();
                    $output .= "Created reviewer user: {$review['name']}<br>";
                }

                $productId = $fallbackProductId;
                $text = $review['review_text'];

                $allProductsStmt = $this->db->query("SELECT id, name FROM products");
                while ($prod = $allProductsStmt->fetch(PDO::FETCH_ASSOC)) {
                    if (stripos($text, $prod['name']) !== false) {
                        $productId = $prod['id'];
                        break;
                    }
                }

                if (stripos($text, 'shampoo') !== false || stripos($text, 'conditioner') !== false) {
                    $stmt = $this->db->prepare("SELECT id FROM products WHERE name LIKE '%Soften%' OR name LIKE '%Balance%' LIMIT 1");
                    $stmt->execute();
                    if ($pid = $stmt->fetchColumn()) {
                        $productId = $pid;
                    }
                }
                if (stripos($text, 'oil') !== false) {
                    $stmt = $this->db->prepare("SELECT id FROM products WHERE name LIKE '%Oil%' LIMIT 1");
                    $stmt->execute();
                    if ($pid = $stmt->fetchColumn()) {
                        $productId = $pid;
                    }
                }

                if (!$productId) {
                    $output .= "Skipping review {$review['id']} - No product found.<br>";
                    continue;
                }

                $stmt = $this->db->prepare(<<<'SQL'
INSERT INTO product_reviews (user_id, product_id, rating, review_text, verified, published, created_at)
VALUES (:user_id, :product_id, 5, :text, 1, 1, :created_at)
SQL
                );

                $stmt->execute([
                    ':user_id' => $userId,
                    ':product_id' => $productId,
                    ':text' => $review['review_text'],
                    ':created_at' => $review['timestamp']
                ]);
                $output .= "Imported review from {$review['name']}<br>";
            }

        } catch (Exception $e) {
            $output .= "Critical Error: " . $e->getMessage();
        }

        $response->getBody()->write($output);
        return $response->withHeader('Content-Type', 'text/html');
    }

    private function readJson(string $filename): array
    {
        $path = __DIR__ . '/../../protected/' . $filename;
        if (!file_exists($path)) {
            throw new Exception("File not found: $path");
        }
        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }
}
