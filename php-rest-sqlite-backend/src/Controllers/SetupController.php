<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use App\Models\User;
use App\Models\Store;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class SetupController extends ApiController
{
    private array $config;
    private PDO $db;
    private Validator $validator;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
        $this->db = Database::get($dbPath);
        $this->validator = new Validator();
    }

    public function checkSetupStatus(Request $request, Response $response): Response
    {
        try {
            // Check if role column exists
            $stmt = $this->db->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasRole = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'role') {
                    $hasRole = true;
                    break;
                }
            }

            if (!$hasRole) {
                return $this->success($response, ['setup_completed' => false]);
            }

            $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE role = :role');
            $stmt->execute([':role' => 'admin']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $isSetup = $result['count'] > 0;

            return $this->success($response, ['setup_completed' => $isSetup]);
        } catch (\Exception $e) {
            // If table doesn't exist or other error, assume not setup
            return $this->success($response, ['setup_completed' => false]);
        }
    }

    public function performSetup(Request $request, Response $response): Response
    {
        // Check if setup already done
        try {
            // Check if role column exists
            $stmt = $this->db->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasRole = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'role') {
                    $hasRole = true;
                    break;
                }
            }

            if ($hasRole) {
                $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE role = :role');
                $stmt->execute([':role' => 'admin']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result['count'] > 0) {
                    return $this->error($response, 'Setup already completed', 403);
                }
            }
        } catch (\Exception $e) {
            // If table doesn't exist or other error, continue with setup
        }

        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'email' => v::notEmpty()->email()->setName('Email'),
                'password' => v::notEmpty()->length(8, null)->setName('Password'),
                'store_name' => v::notEmpty()->setName('Store Name'),
                'store_email' => v::notEmpty()->email()->setName('Store Email'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }

        $email = trim($body['email']);
        $password = trim($body['password']);
        $storeName = trim($body['store_name']);
        $storeEmail = trim($body['store_email']);

        // Initialize all database tables
        try {
            $this->initializeDatabaseTables();
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to initialize database tables: ' . $e->getMessage(), 500);
        }

        // Create admin user
        try {
            $user = User::register($email, $password);
            // Set role to admin
            $stmt = $this->db->prepare('UPDATE users SET role = :role WHERE id = :id');
            $stmt->execute([':role' => 'admin', ':id' => $user->id]);
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to create admin user: ' . $e->getMessage(), 500);
        }

        // Create or update store
        try {
            $store = Store::findByName($storeName);
            if (!$store) {
                $store = new Store([
                    'name' => $storeName,
                    'email' => $storeEmail,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $store->save();
            } else {
                $store->email = $storeEmail;
                $store->updated_at = date('Y-m-d H:i:s');
                $store->save();
            }
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to setup store: ' . $e->getMessage(), 500);
        }

        return $this->success($response, ['message' => 'Setup completed successfully', 'admin_email' => $email, 'store_name' => $storeName], 201);
    }

    private function initializeDatabaseTables(): void
    {
        // Add user roles
        $this->db->exec('ALTER TABLE users ADD COLUMN role TEXT DEFAULT "customer"');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');

        // Add stores table
        $this->db->exec("CREATE TABLE IF NOT EXISTS stores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            address TEXT,
            phone TEXT,
            email TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        // Insert default stores
        $this->db->exec("INSERT OR IGNORE INTO stores (name, description) VALUES ('Siedlung', 'Siedlung store location')");
        $this->db->exec("INSERT OR IGNORE INTO stores (name, description) VALUES ('USA', 'USA store location')");

        // Add products table
        $this->db->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT,
            description TEXT,
            featured_ingredients TEXT,
            all_ingredients TEXT,
            size TEXT,
            cost REAL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_products_type ON products(type)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_products_name ON products(name)');

        // Add inventory table
        $this->db->exec("CREATE TABLE IF NOT EXISTS inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            store_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            min_quantity INTEGER,
            max_quantity INTEGER,
            last_restocked DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(product_id) REFERENCES products(id),
            FOREIGN KEY(store_id) REFERENCES stores(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_inventory_product_store ON inventory(product_id, store_id)');

        // Add orders tables
        $this->db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            order_number TEXT NOT NULL,
            order_date DATETIME,
            fulfillment_status TEXT,
            shipping_date DATETIME,
            shipping_address TEXT NOT NULL,
            phone_number TEXT,
            whatsapp_number TEXT,
            subtotal REAL NOT NULL,
            discount_amount REAL,
            coupon_code TEXT,
            shipping_cost REAL,
            total REAL NOT NULL,
            tracking_number TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            store_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            subtotal REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id),
            FOREIGN KEY(product_id) REFERENCES products(id),
            FOREIGN KEY(store_id) REFERENCES stores(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id)');

        // Add reviews table
        $this->db->exec("CREATE TABLE IF NOT EXISTS product_reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            order_id INTEGER,
            rating INTEGER NOT NULL,
            review_text TEXT,
            verified INTEGER,
            published INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(product_id) REFERENCES products(id),
            FOREIGN KEY(order_id) REFERENCES orders(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_product_reviews_product_id ON product_reviews(product_id)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_product_reviews_user_id ON product_reviews(user_id)');

        // Add bookkeeping tables
        $this->db->exec("CREATE TABLE IF NOT EXISTS income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            amount REAL NOT NULL,
            payment_method TEXT,
            payment_date DATETIME,
            description TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id)
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            vendor TEXT,
            category TEXT NOT NULL,
            amount REAL NOT NULL,
            expense_date DATETIME,
            description TEXT,
            receipt_image_url TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_income_order_id ON income(order_id)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category)');

        // Add referral tables
        $this->db->exec("CREATE TABLE IF NOT EXISTS user_referrals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            referral_code TEXT NOT NULL,
            times_used INTEGER,
            points_earned INTEGER,
            points_redeemed INTEGER,
            points_balance INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS referral_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            referrer_user_id INTEGER NOT NULL,
            referred_user_id INTEGER NOT NULL,
            referral_code TEXT NOT NULL,
            order_id INTEGER,
            points_awarded INTEGER,
            used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(referrer_user_id) REFERENCES users(id),
            FOREIGN KEY(referred_user_id) REFERENCES users(id),
            FOREIGN KEY(order_id) REFERENCES orders(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_user_referrals_user_id ON user_referrals(user_id)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_referral_usage_referrer ON referral_usage(referrer_user_id)');

        // Add coupons tables
        $this->db->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            discount_type TEXT NOT NULL,
            discount_value REAL NOT NULL,
            min_purchase_amount REAL,
            max_uses INTEGER,
            times_used INTEGER,
            valid_from DATETIME,
            valid_until DATETIME,
            is_active INTEGER,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS coupon_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coupon_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            discount_amount REAL NOT NULL,
            used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(coupon_id) REFERENCES coupons(id),
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(order_id) REFERENCES orders(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon_id ON coupon_usage(coupon_id)');

        // Add returns system
        $this->db->exec("CREATE TABLE IF NOT EXISTS returns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            return_number TEXT NOT NULL,
            status TEXT,
            reason TEXT,
            refund_amount REAL,
            refund_method TEXT,
            refund_date DATETIME,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS return_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            return_id INTEGER NOT NULL,
            order_item_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            store_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            refund_amount REAL NOT NULL,
            reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(return_id) REFERENCES returns(id),
            FOREIGN KEY(order_item_id) REFERENCES order_items(id),
            FOREIGN KEY(product_id) REFERENCES products(id),
            FOREIGN KEY(store_id) REFERENCES stores(id)
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_returns_order_id ON returns(order_id)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_return_items_return_id ON return_items(return_id)');

        // Add tax system
        $this->db->exec("CREATE TABLE IF NOT EXISTS tax_rates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            rate REAL NOT NULL,
            region TEXT,
            is_default INTEGER,
            is_active INTEGER,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec('ALTER TABLE orders ADD COLUMN tax_rate REAL');
        $this->db->exec('ALTER TABLE orders ADD COLUMN tax_amount REAL');
        // Insert default tax rates
        $this->db->exec("INSERT OR IGNORE INTO tax_rates (name, rate, region, is_default, is_active, description) VALUES ('Germany VAT', 19.0, 'DE', 1, 1, 'German Value Added Tax')");
        $this->db->exec("INSERT OR IGNORE INTO tax_rates (name, rate, region, is_default, is_active, description) VALUES ('USA Sales Tax', 7.5, 'US', 0, 1, 'US Sales Tax')");

        // Add testimonials table
        $this->db->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            age_range TEXT,
            testimonial_text TEXT NOT NULL,
            image_url TEXT,
            published INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Add password reset fields
        $this->db->exec('ALTER TABLE users ADD COLUMN reset_token TEXT');
        $this->db->exec('ALTER TABLE users ADD COLUMN reset_expires_at DATETIME');
    }
}