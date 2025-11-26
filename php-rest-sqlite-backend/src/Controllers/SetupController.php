<?php

namespace App\Controllers;

use App\Services\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SetupController extends ApiController
{
    private array $config;
    private ?PDO $db = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Check if database is already initialized
     */
    public function checkDatabase(Request $request, Response $response): Response
    {
        $dbPath = $this->config['database']['database_path'] ?? null;

        if (!$dbPath || !file_exists($dbPath)) {
            return $this->success($response, [
                'initialized' => false,
                'message' => 'Database not found. Ready for setup.'
            ]);
        }

        try {
            $db = Database::get($dbPath);
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return $this->success($response, [
                    'initialized' => true,
                    'message' => 'Database already contains data. Setup cannot proceed.'
                ]);
            }

            return $this->success($response, [
                'initialized' => false,
                'message' => 'Database exists but is empty. Ready for setup.'
            ]);
        } catch (\Exception $e) {
            return $this->success($response, [
                'initialized' => false,
                'message' => 'Database not initialized. Ready for setup.'
            ]);
        }
    }

    /**
     * Create admin user
     */
    public function createAdmin(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;

        if (!$email || !$password) {
            return $this->error($response, 'Email and password are required', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'Invalid email format', 400);
        }

        if (strlen($password) < 8) {
            return $this->error($response, 'Password must be at least 8 characters', 400);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);

            // Check if admin already exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $stmt->execute();
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return $this->error($response, 'Admin user already exists', 400);
            }

            // Create admin user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (email, password_hash, role, is_email_verified, created_at)
                VALUES (:email, :password_hash, 'admin', 1, datetime('now'))
            ");
            $stmt->execute([
                ':email' => $email,
                ':password_hash' => $passwordHash
            ]);

            return $this->success($response, [
                'message' => 'Admin user created successfully',
                'user_id' => $db->lastInsertId()
            ], 201);
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to create admin: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import users from JSON
     */
    public function importUsers(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $users = $body['users'] ?? [];

        if (empty($users)) {
            return $this->success($response, ['imported' => 0, 'message' => 'No users to import']);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($users as $user) {
                $stmt = $db->prepare("
                    INSERT INTO users (email, password_hash, display_name, role, created_at)
                    VALUES (:email, :password_hash, :display_name, :role, datetime('now'))
                ");
                $stmt->execute([
                    ':email' => $user['email'],
                    ':password_hash' => password_hash($user['password'] ?? 'password123', PASSWORD_DEFAULT),
                    ':display_name' => $user['display_name'] ?? $user['email'],
                    ':role' => $user['role'] ?? 'customer'
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import stores from JSON
     */
    public function importStores(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $stores = $body['stores'] ?? [];

        if (empty($stores)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($stores as $store) {
                $stmt = $db->prepare("
                    INSERT INTO stores (name, location, address, phone, email, created_at)
                    VALUES (:name, :location, :address, :phone, :email, datetime('now'))
                ");
                $stmt->execute([
                    ':name' => $store['name'],
                    ':location' => $store['location'] ?? null,
                    ':address' => $store['address'] ?? null,
                    ':phone' => $store['phone'] ?? null,
                    ':email' => $store['email'] ?? null
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import products from JSON
     */
    public function importProducts(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $products = $body['products'] ?? [];

        if (empty($products)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($products as $product) {
                $stmt = $db->prepare("
                    INSERT INTO products (name, type, description, cost, featured_ingredients, all_ingredients, size, created_at)
                    VALUES (:name, :type, :description, :cost, :featured_ingredients, :all_ingredients, :size, datetime('now'))
                ");
                $stmt->execute([
                    ':name' => $product['name'],
                    ':type' => $product['type'] ?? null,
                    ':description' => $product['description'] ?? null,
                    ':cost' => $product['cost'] ?? 0,
                    ':featured_ingredients' => $product['featured_ingredients'] ?? null,
                    ':all_ingredients' => $product['all_ingredients'] ?? null,
                    ':size' => $product['size'] ?? null
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import orders from JSON (includes creating users and matching products)
     */
    public function importOrders(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $orders = $body['orders'] ?? [];

        if (empty($orders)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($orders as $order) {
                // Create or find user
                $userEmail = $order['customer_email'];
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $userEmail]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $stmt = $db->prepare("
                        INSERT INTO users (email, password_hash, role, created_at)
                        VALUES (:email, :password_hash, 'customer', datetime('now'))
                    ");
                    $stmt->execute([
                        ':email' => $userEmail,
                        ':password_hash' => password_hash('password123', PASSWORD_DEFAULT)
                    ]);
                    $userId = $db->lastInsertId();
                } else {
                    $userId = $user['id'];
                }

                // Create order
                $stmt = $db->prepare("
                    INSERT INTO orders (user_id, order_number, shipping_address, subtotal, total, order_date, created_at)
                    VALUES (:user_id, :order_number, :shipping_address, :subtotal, :total, :order_date, datetime('now'))
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':order_number' => $order['order_number'] ?? 'ORD-' . uniqid(),
                    ':shipping_address' => $order['shipping_address'] ?? 'N/A',
                    ':subtotal' => $order['subtotal'] ?? 0,
                    ':total' => $order['total'] ?? 0,
                    ':order_date' => $order['order_date'] ?? date('Y-m-d H:i:s')
                ]);
                $orderId = $db->lastInsertId();

                // Create order items
                foreach ($order['items'] ?? [] as $item) {
                    // Find or create product
                    $stmt = $db->prepare("SELECT id FROM products WHERE name = :name");
                    $stmt->execute([':name' => $item['product_name']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        $stmt = $db->prepare("
                            INSERT INTO products (name, cost, created_at)
                            VALUES (:name, :cost, datetime('now'))
                        ");
                        $stmt->execute([
                            ':name' => $item['product_name'],
                            ':cost' => $item['unit_price'] ?? 0
                        ]);
                        $productId = $db->lastInsertId();
                    } else {
                        $productId = $product['id'];
                    }

                    // Create order item
                    $stmt = $db->prepare("
                        INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal, created_at)
                        VALUES (:order_id, :product_id, 1, :quantity, :unit_price, :subtotal, datetime('now'))
                    ");
                    $stmt->execute([
                        ':order_id' => $orderId,
                        ':product_id' => $productId,
                        ':quantity' => $item['quantity'] ?? 1,
                        ':unit_price' => $item['unit_price'] ?? 0,
                        ':subtotal' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0)
                    ]);
                }

                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import coupons from JSON
     */
    public function importCoupons(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $coupons = $body['coupons'] ?? [];

        if (empty($coupons)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($coupons as $coupon) {
                $stmt = $db->prepare("
                    INSERT INTO coupons (code, discount_type, discount_value, min_purchase, max_uses, expires_at, is_active, created_at)
                    VALUES (:code, :discount_type, :discount_value, :min_purchase, :max_uses, :expires_at, 1, datetime('now'))
                ");
                $stmt->execute([
                    ':code' => $coupon['code'],
                    ':discount_type' => $coupon['discount_type'] ?? 'percentage',
                    ':discount_value' => $coupon['discount_value'] ?? 0,
                    ':min_purchase' => $coupon['min_purchase'] ?? 0,
                    ':max_uses' => $coupon['max_uses'] ?? null,
                    ':expires_at' => $coupon['expires_at'] ?? null
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import reviews from JSON
     */
    public function importReviews(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $reviews = $body['reviews'] ?? [];

        if (empty($reviews)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($reviews as $review) {
                $stmt = $db->prepare("
                    INSERT INTO product_reviews (user_id, product_id, rating, review_text, published, created_at)
                    VALUES (:user_id, :product_id, :rating, :review_text, 1, datetime('now'))
                ");
                $stmt->execute([
                    ':user_id' => $review['user_id'] ?? 1,
                    ':product_id' => $review['product_id'] ?? 1,
                    ':rating' => $review['rating'] ?? 5,
                    ':review_text' => $review['review_text'] ?? ''
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import testimonials from JSON
     */
    public function importTestimonials(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $testimonials = $body['testimonials'] ?? [];

        if (empty($testimonials)) {
            return $this->success($response, ['imported' => 0]);
        }

        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            $db->beginTransaction();

            $imported = 0;
            foreach ($testimonials as $testimonial) {
                $stmt = $db->prepare("
                    INSERT INTO testimonials (customer_name, testimonial_text, rating, is_featured, created_at)
                    VALUES (:customer_name, :testimonial_text, :rating, :is_featured, datetime('now'))
                ");
                $stmt->execute([
                    ':customer_name' => $testimonial['customer_name'],
                    ':testimonial_text' => $testimonial['testimonial_text'],
                    ':rating' => $testimonial['rating'] ?? 5,
                    ':is_featured' => $testimonial['is_featured'] ?? 0
                ]);
                $imported++;
            }

            $db->commit();
            return $this->success($response, ['imported' => $imported]);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->error($response, 'Import failed: ' . $e->getMessage(), 500);
        }
    }
}