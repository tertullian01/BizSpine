<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fresh SQLite installs previously had no tables before ALTER-only migrations, and Phinx's
 * SQLite adapter breaks on addColumn(..., ['after' => ...]). This migration establishes core
 * tables (IF NOT EXISTS) so later migrations can add columns safely.
 */
final class BaselineCoreSchemaSqlite extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('PRAGMA foreign_keys = ON;');

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    password_hash TEXT,
    display_name TEXT,
    role TEXT DEFAULT 'customer',
    is_email_verified INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);
SQL
        );

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    provider_user_id TEXT NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    UNIQUE(provider, provider_user_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
        );

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    revoked INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
        );

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    store_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    min_quantity INTEGER DEFAULT 0,
    max_quantity INTEGER DEFAULT NULL,
    price_override REAL,
    last_restocked DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id, store_id),
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
);
SQL
        );

        $this->execute(<<<'SQL'
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
    shipping_address TEXT,
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
    tracking_url TEXT,
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    discount_type TEXT NOT NULL DEFAULT 'percentage',
    discount_value REAL NOT NULL,
    min_purchase_amount REAL DEFAULT 0,
    max_uses INTEGER,
    times_used INTEGER DEFAULT 0,
    valid_from DATETIME,
    valid_until DATETIME,
    is_active INTEGER DEFAULT 1,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL
        );

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupon_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    coupon_id INTEGER NOT NULL,
    user_id INTEGER,
    order_id INTEGER DEFAULT 0,
    discount_amount REAL NOT NULL,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
        );

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS income (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER,
    amount REAL NOT NULL,
    category TEXT,
    payment_method TEXT,
    transaction_id TEXT,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
);
SQL
        );

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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

        $this->execute(<<<'SQL'
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
    }

    public function down(): void
    {
        // Irreversible — baseline is only for empty/test databases.
    }
}
