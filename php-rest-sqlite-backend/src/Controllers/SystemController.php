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
                    ':code' => strtoupper($code),
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
    user_id, store_id, order_number, order_date, fulfillment_status, shipping_address, 
    phone_number, whatsapp_number, subtotal, shipping_cost, total, created_at
) VALUES (
    :user_id, :store_id, :order_number, :order_date, :status, :address, 
    :phone, :whatsapp, :subtotal, :shipping, :total, :created_at
)
SQL
                    );

                    $stmt->execute([
                        ':user_id' => $userId,
                        ':store_id' => $storeId,
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

    public function runMigrations(Request $request, Response $response): Response
    {
        $output = "<h1>Database Migration Log</h1>";

        try {
            $migrations = [];

            // Fix testimonials table - add missing columns
            $stmt = $this->db->query("PRAGMA table_info(testimonials);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('published', $columnNames)) {
                $this->db->exec("ALTER TABLE testimonials ADD COLUMN published INTEGER DEFAULT 0;");
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_published ON testimonials(published);');
                $migrations[] = "Added 'published' column to testimonials table";
            }

            if (!in_array('customer_email', $columnNames)) {
                $this->db->exec("ALTER TABLE testimonials ADD COLUMN customer_email TEXT;");
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_email ON testimonials(customer_email);');
                $migrations[] = "Added 'customer_email' column to testimonials table";
            }

            if (!in_array('age_range', $columnNames)) {
                $this->db->exec("ALTER TABLE testimonials ADD COLUMN age_range TEXT;");
                $migrations[] = "Added 'age_range' column to testimonials table";
            }

            if (!in_array('image_url', $columnNames)) {
                $this->db->exec("ALTER TABLE testimonials ADD COLUMN image_url TEXT;");
                $migrations[] = "Added 'image_url' column to testimonials table";
            }

            if (!in_array('updated_at', $columnNames)) {
                $this->db->exec("ALTER TABLE testimonials ADD COLUMN updated_at DATETIME;");
                $migrations[] = "Added 'updated_at' column to testimonials table";
            }

            // Fix products table - add image_url
            $stmt = $this->db->query("PRAGMA table_info(products);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('image_url', $columnNames)) {
                $this->db->exec("ALTER TABLE products ADD COLUMN image_url TEXT;");
                $migrations[] = "Added 'image_url' column to products table";
            }

            if (!in_array('state', $columnNames)) {
                $this->db->exec("ALTER TABLE products ADD COLUMN state TEXT DEFAULT 'For Sale';");
                $migrations[] = "Added 'state' column to products table";
            }

            // Fix orders table - add store_id
            $stmt = $this->db->query("PRAGMA table_info(orders);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('store_id', $columnNames)) {
                $this->db->exec("ALTER TABLE orders ADD COLUMN store_id INTEGER REFERENCES stores(id) ON DELETE SET NULL;");
                $migrations[] = "Added 'store_id' column to orders table";
            }

            // Fix income table - add category
            $stmt = $this->db->query("PRAGMA table_info(income);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('category', $columnNames)) {
                $this->db->exec("ALTER TABLE income ADD COLUMN category TEXT;");
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_income_category ON income(category);');
                $migrations[] = "Added 'category' column to income table";
            }

            // Create categories table if not exists
            $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='categories';");
            if (!$stmt->fetch()) {
                $this->db->exec(<<<'SQL'
                CREATE TABLE categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    type TEXT NOT NULL,
                    color TEXT,
                    icon TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                SQL);
                $migrations[] = "Created 'categories' table";
            }

            // Fix users table - add profile columns
            $stmt = $this->db->query("PRAGMA table_info(users);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            $userColumns = [
                'first_name' => 'TEXT',
                'last_name' => 'TEXT',
                'country' => 'TEXT',
                'street_line_1' => 'TEXT',
                'street_line_2' => 'TEXT',
                'city' => 'TEXT',
                'state' => 'TEXT',
                'postal_code' => 'TEXT',
                'mobile_number' => 'TEXT',
                'whatsapp_number' => 'TEXT',
                'instagram_link' => 'TEXT',
                'facebook_link' => 'TEXT',
                'is_email_verified' => 'INTEGER DEFAULT 0',
                'last_login' => 'DATETIME',
                'reset_token' => 'TEXT',
                'reset_expires_at' => 'DATETIME'
            ];

            foreach ($userColumns as $col => $type) {
                if (!in_array($col, $columnNames)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN $col $type;");
                    $migrations[] = "Added '$col' column to users table";
                }
            }

            // Fix coupon_usage table
            $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='coupon_usage';");
            if (!$stmt->fetch()) {
                $this->db->exec(<<<'SQL'
                CREATE TABLE coupon_usage (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    coupon_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    order_id INTEGER DEFAULT 0,
                    discount_amount REAL NOT NULL,
                    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                SQL);
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id);');
                $migrations[] = "Created 'coupon_usage' table";
            }

            if (empty($migrations)) {
                $output .= "<p>No migrations needed - database schema is up to date.</p>";
            } else {
                $output .= "<ul><li>" . implode("</li><li>", $migrations) . "</li></ul>";
                $output .= "<p><strong>Migrations completed successfully.</strong></p>";
            }

        } catch (Exception $e) {
            $output .= "<p style='color:red'><strong>Error:</strong> " . $e->getMessage() . "</p>";
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
