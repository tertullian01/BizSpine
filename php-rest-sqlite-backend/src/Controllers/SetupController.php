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

            // Initialize database schema
            $db->exec('PRAGMA foreign_keys = ON;');
            
            // Create users table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                display_name TEXT,
                role TEXT DEFAULT 'customer',
                is_email_verified INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME
            );
            SQL
            );

            // Create stores table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS stores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                location TEXT,
                address TEXT,
                phone TEXT,
                email TEXT,
                currency_symbol TEXT DEFAULT '$',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create products table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT,
                description TEXT,
                featured_ingredients TEXT,
                all_ingredients TEXT,
                size TEXT,
                cost REAL,
                image_url TEXT,
                state TEXT DEFAULT 'For Sale',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create inventory table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                store_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                min_quantity INTEGER DEFAULT 0,
                max_quantity INTEGER DEFAULT NULL,
                last_restocked DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(product_id, store_id),
                FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
            );
            SQL
            );

            // Create orders table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                customer_email TEXT,
                customer_name TEXT,
                store_id INTEGER,
                order_number TEXT UNIQUE NOT NULL,
                order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                fulfillment_status TEXT DEFAULT 'pending',
                shipping_date DATETIME,
                shipping_address TEXT NOT NULL,
                city TEXT,
                state TEXT,
                postal_code TEXT,
                country TEXT,
                phone_number TEXT,
                whatsapp_number TEXT,
                subtotal REAL NOT NULL DEFAULT 0,
                discount_amount REAL DEFAULT 0,
                coupon_code TEXT,
                shipping_cost REAL DEFAULT 0,
                total REAL NOT NULL DEFAULT 0,
                tracking_number TEXT,
                shipping_method TEXT,
                shipping_carrier TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE SET NULL
            );
            SQL
            );

            // Create order_items table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                store_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
                FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE RESTRICT
            );
            SQL
            );

            // Create product_reviews table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
                review_text TEXT,
                published INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
            );
            SQL
            );

            // Create coupons table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS coupons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                discount_type TEXT NOT NULL DEFAULT 'percentage',
                discount_value REAL NOT NULL,
                min_purchase REAL DEFAULT 0,
                max_uses INTEGER,
                times_used INTEGER DEFAULT 0,
                expires_at DATETIME,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create coupon_usage table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS coupon_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                coupon_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                order_id INTEGER DEFAULT 0,
                discount_amount REAL NOT NULL,
                used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            SQL
            );

            // Create testimonials table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS testimonials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                testimonial_text TEXT NOT NULL,
                rating INTEGER DEFAULT 5,
                is_featured INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create income table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS income (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER,
                amount REAL NOT NULL,
                category TEXT,
                payment_method TEXT,
                payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                description TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
            );
            SQL
            );

            // Create categories table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                color TEXT,
                icon TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create expenses table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER,
                vendor TEXT,
                category TEXT NOT NULL,
                amount REAL NOT NULL,
                expense_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                description TEXT,
                receipt_image_url TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
            );
            SQL
            );

            // Create tax_rates table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tax_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                rate REAL NOT NULL,
                region TEXT,
                is_default INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Create returns table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS returns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                return_number TEXT UNIQUE NOT NULL,
                status TEXT DEFAULT 'requested',
                reason TEXT,
                refund_amount REAL DEFAULT 0,
                refund_method TEXT,
                refund_date DATETIME,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            SQL
            );

            // Create return_items table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS return_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_id INTEGER NOT NULL,
                order_item_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                store_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                refund_amount REAL NOT NULL,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE,
                FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
                FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
                FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE RESTRICT
            );
            SQL
            );

            // Create user_referrals table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_referrals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                referral_code TEXT NOT NULL UNIQUE,
                times_used INTEGER DEFAULT 0,
                points_earned INTEGER DEFAULT 0,
                points_redeemed INTEGER DEFAULT 0,
                points_balance INTEGER DEFAULT 0,
                discount_type TEXT DEFAULT 'percentage',
                discount_amount REAL DEFAULT 10.0,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            SQL
            );

            // Create referral_usage table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS referral_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                referrer_user_id INTEGER NOT NULL,
                referred_user_id INTEGER NOT NULL,
                referral_code TEXT NOT NULL,
                order_id INTEGER,
                points_awarded INTEGER DEFAULT 0,
                used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(referred_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
            );
            SQL
            );

            // Create settings table
            $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                value TEXT,
                group_name TEXT DEFAULT 'general',
                type TEXT DEFAULT 'string',
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL
            );

            // Insert default store
            $db->exec("INSERT OR IGNORE INTO stores (name, description) VALUES ('Default Store', 'Default store location')");

            // Add additional fields to users table
            try { $db->exec('ALTER TABLE users ADD COLUMN reset_token TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN reset_expires_at DATETIME'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN first_name TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN last_name TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN country TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN street_line_1 TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN street_line_2 TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN city TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN state TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN postal_code TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN mobile_number TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN whatsapp_number TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN instagram_link TEXT'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN facebook_link TEXT'); } catch (\Exception $e) {}

            // Add tax fields to orders table
            try { $db->exec('ALTER TABLE orders ADD COLUMN tax_rate REAL DEFAULT 0'); } catch (\Exception $e) {}
            try { $db->exec('ALTER TABLE orders ADD COLUMN tax_amount REAL DEFAULT 0'); } catch (\Exception $e) {}

            // Create indexes
            $db->exec('CREATE INDEX IF NOT EXISTS idx_income_order ON income(order_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_income_date ON income(payment_date);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_income_category ON income(category);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_expenses_order ON expenses(order_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses(expense_date);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_expenses_vendor ON expenses(vendor);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_tax_rates_active ON tax_rates(is_active);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_tax_rates_region ON tax_rates(region);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_returns_order ON returns(order_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_returns_user ON returns(user_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_returns_status ON returns(status);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_return_items_return ON return_items(return_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_user_referrals_user ON user_referrals(user_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_user_referrals_code ON user_referrals(referral_code);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_referrer ON referral_usage(referrer_user_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_referred ON referral_usage(referred_user_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_code ON referral_usage(referral_code);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id);');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id);');

            // Insert default tax rates
            $db->exec("INSERT OR IGNORE INTO tax_rates (name, rate, region, is_default, description) VALUES ('Germany VAT', 19.0, 'DE', 1, 'Standard German VAT rate')");
            $db->exec("INSERT OR IGNORE INTO tax_rates (name, rate, region, is_default, description) VALUES ('USA Sales Tax', 7.5, 'US', 0, 'Average US sales tax')");

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
                    INSERT INTO products (name, type, description, cost, featured_ingredients, all_ingredients, size, image_url, state, created_at)
                    VALUES (:name, :type, :description, :cost, :featured_ingredients, :all_ingredients, :size, :image_url, :state, datetime('now'))
                ");
                $stmt->execute([
                    ':name' => $product['name'],
                    ':type' => $product['type'] ?? null,
                    ':description' => $product['description'] ?? null,
                    ':cost' => $product['cost'] ?? 0,
                    ':featured_ingredients' => $product['featured_ingredients'] ?? null,
                    ':all_ingredients' => $product['all_ingredients'] ?? null,
                    ':size' => $product['size'] ?? null,
                    ':image_url' => $product['image_url'] ?? null,
                    ':state' => $product['state'] ?? 'For Sale'
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
                    INSERT INTO orders (user_id, store_id, order_number, shipping_address, subtotal, total, order_date, created_at)
                    VALUES (:user_id, :store_id, :order_number, :shipping_address, :subtotal, :total, :order_date, datetime('now'))
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':store_id' => $order['store_id'] ?? 1,
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
                    ':code' => strtoupper($coupon['code']),
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

    /**
     * Run database migrations to fix schema issues
     */
    public function runMigrations(Request $request, Response $response): Response
    {
        try {
            $dbPath = $this->config['database']['database_path'];
            $db = Database::get($dbPath);
            
            $migrations = [];

            // Fix testimonials table - add missing columns
            $stmt = $db->query("PRAGMA table_info(testimonials);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('published', $columnNames)) {
                $db->exec("ALTER TABLE testimonials ADD COLUMN published INTEGER DEFAULT 0;");
                $db->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_published ON testimonials(published);');
                $migrations[] = "Added 'published' column to testimonials table";
            }

            if (!in_array('customer_email', $columnNames)) {
                $db->exec("ALTER TABLE testimonials ADD COLUMN customer_email TEXT;");
                $db->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_email ON testimonials(customer_email);');
                $migrations[] = "Added 'customer_email' column to testimonials table";
            }

            if (!in_array('age_range', $columnNames)) {
                $db->exec("ALTER TABLE testimonials ADD COLUMN age_range TEXT;");
                $migrations[] = "Added 'age_range' column to testimonials table";
            }

            if (!in_array('image_url', $columnNames)) {
                $db->exec("ALTER TABLE testimonials ADD COLUMN image_url TEXT;");
                $migrations[] = "Added 'image_url' column to testimonials table";
            }

            // SQLite doesn't allow CURRENT_TIMESTAMP default in ALTER TABLE
            // Use NULL default instead for updated_at
            if (!in_array('updated_at', $columnNames)) {
                $db->exec("ALTER TABLE testimonials ADD COLUMN updated_at DATETIME;");
                $migrations[] = "Added 'updated_at' column to testimonials table";
            }

            // Fix products table - add image_url
            $stmt = $db->query("PRAGMA table_info(products);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('image_url', $columnNames)) {
                $db->exec("ALTER TABLE products ADD COLUMN image_url TEXT;");
                $migrations[] = "Added 'image_url' column to products table";
            }

            if (!in_array('state', $columnNames)) {
                $db->exec("ALTER TABLE products ADD COLUMN state TEXT DEFAULT 'For Sale';");
                $migrations[] = "Added 'state' column to products table";
            }

            // Fix orders table - add store_id
            $stmt = $db->query("PRAGMA table_info(orders);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('store_id', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN store_id INTEGER;");
                $migrations[] = "Added 'store_id' column to orders table";
            }

            if (!in_array('shipping_method', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN shipping_method TEXT;");
                $migrations[] = "Added 'shipping_method' column to orders table";
            }

            if (!in_array('shipping_carrier', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN shipping_carrier TEXT;");
                $migrations[] = "Added 'shipping_carrier' column to orders table";
            }

            if (!in_array('customer_email', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN customer_email TEXT;");
                $migrations[] = "Added 'customer_email' column to orders table";
            }

            if (!in_array('customer_name', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN customer_name TEXT;");
                $migrations[] = "Added 'customer_name' column to orders table";
            }

            if (!in_array('city', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN city TEXT;");
                $migrations[] = "Added 'city' column to orders table";
            }

            if (!in_array('state', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN state TEXT;");
                $migrations[] = "Added 'state' column to orders table";
            }

            if (!in_array('postal_code', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN postal_code TEXT;");
                $migrations[] = "Added 'postal_code' column to orders table";
            }

            if (!in_array('country', $columnNames)) {
                $db->exec("ALTER TABLE orders ADD COLUMN country TEXT;");
                $migrations[] = "Added 'country' column to orders table";
            }

            // Fix stores table - add currency_symbol
            $stmt = $db->query("PRAGMA table_info(stores);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('currency_symbol', $columnNames)) {
                $db->exec("ALTER TABLE stores ADD COLUMN currency_symbol TEXT DEFAULT '$';");
                $migrations[] = "Added 'currency_symbol' column to stores table";
            }

            // Fix user_referrals table - add discount info and status
            $stmt = $db->query("PRAGMA table_info(user_referrals);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            if (!in_array('discount_type', $columnNames)) {
                $db->exec("ALTER TABLE user_referrals ADD COLUMN discount_type TEXT DEFAULT 'percentage';");
                $migrations[] = "Added 'discount_type' column to user_referrals table";
            }
            if (!in_array('discount_amount', $columnNames)) {
                $db->exec("ALTER TABLE user_referrals ADD COLUMN discount_amount REAL DEFAULT 10.0;");
                $migrations[] = "Added 'discount_amount' column to user_referrals table";
            }
            if (!in_array('status', $columnNames)) {
                $db->exec("ALTER TABLE user_referrals ADD COLUMN status TEXT DEFAULT 'active';");
                $migrations[] = "Added 'status' column to user_referrals table";
            }

            $db->exec('CREATE INDEX IF NOT EXISTS idx_testimonials_created ON testimonials(created_at);');

            if (empty($migrations)) {
                return $this->success($response, [
                    'message' => 'No migrations needed - database schema is up to date',
                    'migrations' => []
                ]);
            }

            return $this->success($response, [
                'message' => 'Migrations completed successfully',
                'migrations' => $migrations
            ]);
        } catch (\Exception $e) {
            return $this->error($response, 'Migration failed: ' . $e->getMessage(), 500);
        }
    }
}