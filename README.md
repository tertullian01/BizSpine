# BizSpine

The backbone for storefront and business operations: a PHP/SQLite REST API with customer tracking, inventory management, multi-store locations, orders, and reviews.

> **BizSpine** is a product of [Tech Diplomacy](https://techdiplomacy.dev/) — software where complex systems meet the people who rely on them.

## 📋 Table of Contents

- [Overview](#overview)
- [Technology Stack](#technology-stack)
- [Architecture Analysis](#architecture-analysis)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [Security Features](#security-features)
- [Installation & Setup](#installation--setup)
- [Production deployment](#production-deployment)
- [Testing](#testing)
- [Project Structure](#project-structure)

## 🎯 Overview

This project provides a production-ready RESTful API backend built with PHP and SQLite. It implements modern authentication patterns using JWT tokens, follows MVC architecture principles, and includes comprehensive test coverage. The system is designed to be lightweight, portable, and easy to deploy while maintaining enterprise-level security and code quality standards.

## 🛠 Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| **Language** | PHP | 7.4+ / 8.0+ |
| **Framework** | Slim Framework | 4.0 |
| **Database** | SQLite | 3.x |
| **Authentication** | JWT (Firebase PHP-JWT) | 6.11 |
| **Testing** | PHPUnit | 9.0 |
| **Environment** | phpdotenv | 5.0 |
| **HTTP** | PSR-7 (Slim PSR7) | 1.0 |

## 🏗 Architecture Analysis

### Design Patterns

1. **MVC (Model-View-Controller)**
   - **Models**: Data structures representing database entities ([`User`](backend/src/Models/User.php:1), [`Product`](backend/src/Models/Product.php:1), [`Store`](backend/src/Models/Store.php:1), [`Inventory`](backend/src/Models/Inventory.php:1), [`Order`](backend/src/Models/Order.php:1), [`OrderItem`](backend/src/Models/OrderItem.php:1), [`ProductReview`](backend/src/Models/ProductReview.php:1), [`Testimonial`](backend/src/Models/Testimonial.php:1), [`Income`](backend/src/Models/Income.php:1), [`Expense`](backend/src/Models/Expense.php:1), [`UserReferral`](backend/src/Models/UserReferral.php:1), [`ReferralUsage`](backend/src/Models/ReferralUsage.php:1), [`Coupon`](backend/src/Models/Coupon.php:1), [`CouponUsage`](backend/src/Models/CouponUsage.php:1), [`TaxRate`](backend/src/Models/TaxRate.php:1), [`OrderReturn`](backend/src/Models/OrderReturn.php:1), [`ReturnItem`](backend/src/Models/ReturnItem.php:1))
   - **Controllers**: Business logic handlers ([`AuthController`](backend/src/Controllers/AuthController.php:1), [`ProductController`](backend/src/Controllers/ProductController.php:1), [`StoreController`](backend/src/Controllers/StoreController.php:1), [`InventoryController`](backend/src/Controllers/InventoryController.php:1), [`OrderController`](backend/src/Controllers/OrderController.php:1), [`ReviewController`](backend/src/Controllers/ReviewController.php:1), [`TestimonialController`](backend/src/Controllers/TestimonialController.php:1), [`BookkeepingController`](backend/src/Controllers/BookkeepingController.php:1), [`ReferralController`](backend/src/Controllers/ReferralController.php:1), [`CouponController`](backend/src/Controllers/CouponController.php:1), [`TaxController`](backend/src/Controllers/TaxController.php:1), [`ReturnController`](backend/src/Controllers/ReturnController.php:1))
   - **Views**: JSON responses (RESTful API)

2. **Middleware Pattern**
   - [`AuthMiddleware`](backend/src/Middleware/AuthMiddleware.php:1): JWT token validation and authentication
   - Intercepts requests to protected routes
   - Validates token signature, expiration, and structure

3. **Service Layer Pattern**
   - [`Database`](backend/src/Services/Database.php:1) service: Centralized database connection management
   - Singleton pattern for PDO instance
   - Automatic directory creation and permissions handling

4. **Dependency Injection**
   - Controllers accept configuration and database instances
   - Enables easy testing with mock objects
   - Supports test mode for unit testing without side effects

### Code Quality Features

- **PSR-4 Autoloading**: Organized namespace structure (`App\Controllers`, `App\Models`, etc.)
- **Type Safety**: Strict type declarations (`declare(strict_types=1)`)
- **Error Handling**: Try-catch blocks with appropriate HTTP status codes
- **Separation of Concerns**: Clear separation between routing, business logic, and data access
- **Test-Driven Development**: Comprehensive unit test coverage (100% for Store functionality)

## 💾 Database Schema

### Tables Overview

```sql
users
├── id (INTEGER PRIMARY KEY)
├── email (TEXT UNIQUE)
├── password_hash (TEXT)
├── display_name (TEXT)
├── is_email_verified (INTEGER)
├── created_at (DATETIME)
└── last_login (DATETIME)

products
├── id (INTEGER PRIMARY KEY)
├── name (TEXT NOT NULL)
├── type (TEXT)
├── description (TEXT)
├── featured_ingredients (TEXT)
├── all_ingredients (TEXT)
├── size (TEXT)
├── cost (REAL)
├── created_at (DATETIME)
└── updated_at (DATETIME)

stores
├── id (INTEGER PRIMARY KEY)
├── name (TEXT NOT NULL UNIQUE)
├── description (TEXT)
├── address (TEXT)
├── phone (TEXT)
├── email (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

inventory
├── id (INTEGER PRIMARY KEY)
├── product_id (INTEGER FK → products.id)
├── store_id (INTEGER FK → stores.id)
├── quantity (INTEGER NOT NULL)
├── min_quantity (INTEGER)
├── max_quantity (INTEGER)
├── price_override (REAL)
├── last_restocked (DATETIME)
├── created_at (DATETIME)
└── updated_at (DATETIME)

orders
├── id (INTEGER PRIMARY KEY)
├── user_id (INTEGER FK → users.id)
├── order_number (TEXT UNIQUE)
├── order_date (DATETIME)
├── fulfillment_status (TEXT)
├── shipping_date (DATETIME)
├── shipping_address (TEXT NOT NULL)
├── phone_number (TEXT)
├── whatsapp_number (TEXT)
├── subtotal (REAL)
├── discount_amount (REAL)
├── coupon_code (TEXT)
├── shipping_cost (REAL)
├── total (REAL)
├── tracking_number (TEXT)
├── tracking_url (TEXT)
├── notes (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

order_items
├── id (INTEGER PRIMARY KEY)
├── order_id (INTEGER FK → orders.id)
├── product_id (INTEGER FK → products.id)
├── store_id (INTEGER FK → stores.id)
├── quantity (INTEGER NOT NULL)
├── unit_price (REAL)
├── subtotal (REAL)
└── created_at (DATETIME)

refresh_tokens
├── id (INTEGER PRIMARY KEY)
├── user_id (INTEGER FK → users.id)
├── token_hash (TEXT)
├── revoked (INTEGER)
├── created_at (DATETIME)
└── expires_at (DATETIME)

user_providers
├── id (INTEGER PRIMARY KEY)
├── user_id (INTEGER FK → users.id)
├── provider (TEXT)
├── provider_user_id (TEXT)
├── access_token (TEXT)
├── refresh_token (TEXT)
└── token_expires_at (DATETIME)

product_reviews
├── id (INTEGER PRIMARY KEY)
├── user_id (INTEGER FK → users.id)
├── product_id (INTEGER FK → products.id)
├── order_id (INTEGER FK → orders.id)
├── rating (INTEGER NOT NULL, 1-5)
├── review_text (TEXT)
├── verified (INTEGER DEFAULT 0)
├── published (INTEGER DEFAULT 0)
├── created_at (DATETIME)
└── updated_at (DATETIME)

testimonials
├── id (INTEGER PRIMARY KEY)
├── customer_name (TEXT NOT NULL)
├── customer_email (TEXT NOT NULL)
├── age_range (TEXT)
├── testimonial_text (TEXT NOT NULL)
├── image_url (TEXT)
├── published (INTEGER DEFAULT 0)
├── created_at (DATETIME)
└── updated_at (DATETIME)

income
├── id (INTEGER PRIMARY KEY)
├── order_id (INTEGER FK → orders.id)
├── amount (REAL NOT NULL)
├── payment_method (TEXT)
├── payment_date (DATETIME)
├── description (TEXT)
├── notes (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

expenses
├── id (INTEGER PRIMARY KEY)
├── order_id (INTEGER FK → orders.id)
├── vendor (TEXT)
├── category (TEXT NOT NULL)
├── amount (REAL NOT NULL)
├── expense_date (DATETIME)
├── description (TEXT)
├── receipt_image_url (TEXT)
├── notes (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

user_referrals
├── id (INTEGER PRIMARY KEY)
├── user_id (INTEGER FK → users.id, UNIQUE)
├── referral_code (TEXT UNIQUE)
├── times_used (INTEGER DEFAULT 0)
├── points_earned (INTEGER DEFAULT 0)
├── points_redeemed (INTEGER DEFAULT 0)
├── points_balance (INTEGER DEFAULT 0)
├── created_at (DATETIME)
└── updated_at (DATETIME)

referral_usage
├── id (INTEGER PRIMARY KEY)
├── referrer_user_id (INTEGER FK → users.id)
├── referred_user_id (INTEGER FK → users.id)
├── referral_code (TEXT)
├── order_id (INTEGER FK → orders.id)
├── points_awarded (INTEGER)
└── used_at (DATETIME)

referral_redemptions
├── id (INTEGER PRIMARY KEY)
├── user_referral_id (INTEGER FK → user_referrals.id)
├── points_redeemed (INTEGER NOT NULL)
├── order_id (INTEGER)
├── notes (TEXT)
└── redeemed_at (DATETIME)

email_logs
├── id (INTEGER PRIMARY KEY)
├── recipient (TEXT)
├── subject (TEXT)
├── body (TEXT)
├── status (TEXT)
├── error_message (TEXT)
└── sent_at (DATETIME)

coupons
├── id (INTEGER PRIMARY KEY)
├── code (TEXT UNIQUE)
├── discount_type (TEXT) - 'percentage' or 'fixed'
├── discount_value (REAL)
├── min_purchase_amount (REAL)
├── max_uses (INTEGER)
├── times_used (INTEGER DEFAULT 0)
├── valid_from (DATETIME)
├── valid_until (DATETIME)
├── is_active (INTEGER DEFAULT 1)
├── description (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

coupon_usage
├── id (INTEGER PRIMARY KEY)
├── coupon_id (INTEGER FK → coupons.id)
├── user_id (INTEGER FK → users.id)
├── order_id (INTEGER FK → orders.id)
├── discount_amount (REAL)
└── used_at (DATETIME)

tax_rates
├── id (INTEGER PRIMARY KEY)
├── name (TEXT NOT NULL)
├── rate (REAL NOT NULL) - Percentage
├── region (TEXT)
├── is_default (INTEGER DEFAULT 0)
├── is_active (INTEGER DEFAULT 1)
├── description (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)
```

**Note:** Orders table now includes `tax_rate` and `tax_amount` fields for tax tracking.

returns
├── id (INTEGER PRIMARY KEY)
├── order_id (INTEGER FK → orders.id)
├── user_id (INTEGER FK → users.id)
├── return_number (TEXT UNIQUE)
├── status (TEXT) - requested, approved, rejected, completed
├── reason (TEXT)
├── refund_amount (REAL)
├── refund_method (TEXT)
├── refund_date (DATETIME)
├── notes (TEXT)
├── created_at (DATETIME)
└── updated_at (DATETIME)

return_items
├── id (INTEGER PRIMARY KEY)
├── return_id (INTEGER FK → returns.id)
├── order_item_id (INTEGER FK → order_items.id)
├── product_id (INTEGER FK → products.id)
├── store_id (INTEGER FK → stores.id)
├── quantity (INTEGER)
├── refund_amount (REAL)
├── reason (TEXT)
└── created_at (DATETIME)
```

### Database Features

- **Foreign Key Constraints**: Enabled with `PRAGMA foreign_keys = ON`
- **Cascade Deletes**: User deletion cascades to related tokens, providers, orders, and reviews; product/store deletion cascades to inventory; order deletion cascades to order items and sets review order_id to NULL
- **Unique Constraints**: Email uniqueness, store name uniqueness, product-store combination uniqueness in inventory, order number uniqueness
- **Automatic Timestamps**: `created_at` and `updated_at` fields
- **Indexing**: Primary keys automatically indexed, plus custom indexes on inventory, orders, and reviews for performance
- **Check Constraints**: Rating must be between 1 and 5

## 🔌 API Endpoints

### Authentication Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | Public | Register new user account |
| POST | `/auth/login` | Public | Login and receive JWT token |
| POST | `/auth/logout` | Protected | Logout current user |
| POST | `/auth/refresh` | Protected | Refresh access token |

**Authentication Flow:**
1. User registers with email/password (minimum 8 characters)
2. Password is hashed using `PASSWORD_DEFAULT` (bcrypt)
3. Login returns JWT access token (15-minute expiration)
4. Token must be included in `Authorization: Bearer <token>` header for protected routes

### Product Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/products` | Public | List all products |
| GET | `/products/{id}` | Public | Get product by ID |
| POST | `/products` | Protected | Create new product |
| PUT | `/products/{id}` | Protected | Update existing product |
| DELETE | `/products/{id}` | Protected | Delete product |

**Product Fields:**
- `name` (required): Product name
- `type`: Product category/type
- `description`: Detailed description
- `featured_ingredients`: Key ingredients
- `all_ingredients`: Complete ingredient list
- `size`: Product size/dimensions
- `cost`: Product price

### Store Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/stores` | Public | List all stores |
| GET | `/stores/{id}` | Public | Get store by ID |
| POST | `/stores` | Protected | Create new store |
| PUT | `/stores/{id}` | Protected | Update existing store |
| DELETE | `/stores/{id}` | Protected | Delete store |

**Store Validation:**
- Store `name` must be either "Siedlung" or "USA"
- Names are unique (enforced at database level)
- All CRUD operations include proper error handling

**Store Fields:**
- `name` (required): Store name (Siedlung or USA)
- `description`: Store description
- `address`: Physical address
- `phone`: Contact phone number
- `email`: Contact email address

### Inventory Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/inventory` | Public | List all inventory records |
| GET | `/inventory/{id}` | Public | Get inventory record by ID |
| GET | `/inventory/product/{id}` | Public | Get inventory for specific product |
| GET | `/inventory/store/{id}` | Public | Get inventory for specific store |
| GET | `/inventory/low-stock` | Public | Get low stock items |
| POST | `/inventory` | Protected | Create new inventory record |
| PUT | `/inventory/{id}` | Protected | Update inventory record |
| POST | `/inventory/{id}/adjust` | Protected | Adjust inventory quantity |
| DELETE | `/inventory/{id}` | Protected | Delete inventory record |

**Inventory Features:**
- Track product quantities per store location
- Set minimum and maximum stock levels
- Low stock alerts (quantity ≤ min_quantity)
- Automatic timestamp tracking for restocking
- Prevent duplicate product-store combinations
- Quantity adjustment with validation (prevents negative stock)

**Inventory Fields:**
- `product_id` (required): Product reference
- `store_id` (required): Store reference
- `quantity` (required): Current stock quantity
- `min_quantity`: Minimum stock threshold
- `max_quantity`: Maximum stock capacity
- `price_override`: Store-specific price override
- `last_restocked`: Last restock timestamp

### Order Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders` | Protected | List all orders (admin) |
| GET | `/orders/my` | Protected | Get current user's orders |
| GET | `/orders/{id}` | Protected | Get order by ID |
| POST | `/orders` | Protected | Create new order |
| PUT | `/orders/{id}` | Protected | Update order (status, tracking, etc.) |
| POST | `/orders/{id}/cancel` | Protected | Cancel order and restore inventory |

**Order Features:**
- Automatic inventory deduction on order creation
- Inventory restoration on order cancellation
- Order status tracking (pending, processing, shipped, delivered, cancelled)
- Automatic order number generation
- Support for discount codes and shipping costs
- Line item tracking with product and store details
- Store-specific pricing via inventory price overrides
- Prevents cancellation of shipped/delivered orders

**Order Fields:**
- `shipping_address` (required): Delivery address
- `phone_number`: Contact phone
- `whatsapp_number`: WhatsApp contact
- `items` (required): Array of order items with product_id, store_id, quantity
- `coupon_code`: Discount coupon code
- `discount_amount`: Discount value
- `shipping_cost`: Shipping fee
- `tracking_number`: Shipment tracking number
- `tracking_url`: URL to track the shipment
- `notes`: Order notes

**Order Status Values:**
- `pending`: Order placed, awaiting processing
- `processing`: Order being prepared
- `shipped`: Order shipped to customer
- `delivered`: Order delivered successfully
- `cancelled`: Order cancelled

### Review Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/reviews` | Public | List all published reviews |
| GET | `/reviews/product/{id}` | Public | Get published reviews for product |
| GET | `/reviews/my` | Protected | Get current user's reviews |
| GET | `/reviews/{id}` | Protected | Get review by ID |
| POST | `/reviews` | Protected | Create new review |
| PUT | `/reviews/{id}` | Protected | Update own review |
| DELETE | `/reviews/{id}` | Protected | Delete own review |
| POST | `/reviews/{id}/publish` | Protected | Publish review (admin) |
| POST | `/reviews/{id}/unpublish` | Protected | Unpublish review (admin) |

**Review Features:**
- Automatic purchase verification (verified = true if user purchased product)
- Rating validation (1-5 stars)
- Only published reviews visible to public
- Users can only edit/delete their own unpublished reviews
- Cannot edit published reviews
- Moderation workflow (verified and published flags)

**Review Fields:**
- `product_id` (required): Product being reviewed
- `rating` (required): 1-5 star rating
- `review_text`: Written review content
- `verified`: Auto-set to true if user purchased product
- `published`: Default false, must be published by admin
- `order_id`: Auto-linked to purchase order if verified

### Testimonial Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/testimonials` | Public | List all published testimonials |
| POST | `/testimonials` | Public | Submit new testimonial |
| GET | `/testimonials/admin` | Protected | List all testimonials (admin) |
| GET | `/testimonials/{id}` | Protected | Get testimonial by ID |
| PUT | `/testimonials/{id}` | Protected | Update testimonial (admin) |
| DELETE | `/testimonials/{id}` | Protected | Delete testimonial (admin) |
| POST | `/testimonials/{id}/publish` | Protected | Publish testimonial |
| POST | `/testimonials/{id}/unpublish` | Protected | Unpublish testimonial |

**Testimonial Features:**
- Public submission (no authentication required)
- Email validation
- Age range validation (18-24, 25-34, 35-44, 45-54, 55-64, 65+)
- Optional image support
- Moderation workflow with published flag
- Only published testimonials visible to public

**Testimonial Fields:**
- `customer_name` (required): Customer's name
- `customer_email` (required): Valid email address
- `age_range`: Age bracket (optional, validated)
- `testimonial_text` (required): Testimonial content
- `image_url`: Optional customer photo URL
- `published`: Default false, requires admin approval

### Bookkeeping Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/bookkeeping/income` | Protected | List all income records |
| GET | `/bookkeeping/income/{id}` | Protected | Get income record by ID |
| POST | `/bookkeeping/income` | Protected | Create income record |
| DELETE | `/bookkeeping/income/{id}` | Protected | Delete income record |
| GET | `/bookkeeping/expenses` | Protected | List all expenses |
| GET | `/bookkeeping/expenses/{id}` | Protected | Get expense by ID |
| POST | `/bookkeeping/expenses` | Protected | Create expense record |
| PUT | `/bookkeeping/expenses/{id}` | Protected | Update expense record |
| DELETE | `/bookkeeping/expenses/{id}` | Protected | Delete expense record |
| GET | `/bookkeeping/summary` | Protected | Get financial summary |
| POST | `/orders/{id}/payment` | Protected | Add payment to order (creates income) |

**Bookkeeping Features:**
- Automatic income tracking when payment is added to order
- Automatic shipping expense creation when order is shipped with tracking
- Manual expense entry with receipt image support
- Financial summary with profit calculation
- Expense categorization and vendor tracking
- Order linkage for income and shipping expenses

**Income Fields:**
- `order_id`: Linked order (auto-set for order payments)
- `amount` (required): Income amount
- `payment_method`: Payment type (Credit Card, Cash, etc.)
- `payment_date`: When payment was received
- `description`: Income description
- `notes`: Additional notes

**Expense Fields:**
- `vendor`: Vendor/supplier name
- `category` (required): Expense category
- `amount` (required): Expense amount
- `expense_date`: When expense occurred
- `description`: Expense description
- `receipt_image_url`: Receipt image URL
- `notes`: Additional notes
- `order_id`: Linked order (auto-set for shipping)

### Referral Program Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/referrals/my` | Protected | Get user's referral code and stats |
| GET | `/referrals/my/usage` | Protected | Get referral usage history |
| POST | `/referrals/redeem` | Protected | Redeem points |

**Referral Program Features:**
- Automatic referral code generation for each user
- Points awarded when referral code is used (100 points per referral)
- Referral codes can only be used by new users on their first purchase
- Users cannot use their own referral code
- Track times used, points earned, and points redeemed
- Points balance calculation
- Referral usage history with order linkage

**How It Works:**
1. User gets their unique referral code via GET /referrals/my
2. New user includes referral_code in their first order
3. System validates: must be first purchase, cannot be own code, code must exist
4. Referrer earns 100 points automatically
5. Usage is tracked in referral_usage table
6. Referrer can redeem points via POST /referrals/redeem

**Referral Fields:**
- `referral_code`: Auto-generated unique code (REF-XXXXXXXX)
- `times_used`: Number of successful referrals
- `points_earned`: Total points earned from referrals
- `points_redeemed`: Total points redeemed
- `points_balance`: Available points (earned - redeemed)

### Health Check

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/health` | Public | API health status |

## 🔒 Security Features

### Authentication & Authorization

1. **JWT Token-Based Authentication**
   - Tokens signed with HS256 algorithm
   - Configurable secret key (environment-based)
   - Short-lived access tokens (15 minutes default)
   - Token includes issuer, issued-at, expiration, and subject (user ID)

2. **Password Security**
   - Minimum 8-character requirement
   - Bcrypt hashing with `PASSWORD_DEFAULT`
   - Automatic salt generation
   - Password verification using `password_verify()`

3. **Input Validation**
   - Email format validation using `FILTER_VALIDATE_EMAIL`
   - Required field validation
   - Type checking and sanitization
   - SQL injection prevention via prepared statements

4. **Error Handling**
   - Appropriate HTTP status codes (400, 401, 404, 409, 500)
   - Generic error messages (no sensitive data leakage)
   - Detailed logging for debugging (error_log)

5. **Database Security**
   - Prepared statements for all queries
   - Parameter binding to prevent SQL injection
   - Foreign key constraints enabled
   - Unique constraints on sensitive fields

### HTTP Security Headers

**CORS (Cross-Origin Resource Sharing)**:
- Handled by `CorsMiddleware`
- Allowed Origins configured in `protected/config/config.php` (e.g., `https://siedlung.nakednettle.com`, `https://nakednettle.com`, `https://dashboard.nakednettle.com`)

**Apache Configuration** (`.htaccess`):
- URL rewriting for clean API endpoints

## 📦 Installation & Setup

### Prerequisites

- PHP 7.4 or higher (8.0+ recommended)
- Composer (dependency manager)
- SQLite 3.x
- Web server (Apache/Nginx) or PHP built-in server

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd BizSpine
   ```

2. **Install dependencies**
   ```bash
   cd backend
   composer install
   ```

3. **Initialize the database**
   ```bash
   php protected/scripts/init_db.php
   php protected/scripts/add_products_table.php
   php protected/scripts/add_stores_table.php
   php protected/scripts/add_inventory_table.php
   php protected/scripts/add_orders_table.php
   php protected/scripts/add_reviews_table.php
   php protected/scripts/add_testimonials_table.php
   php protected/scripts/add_bookkeeping_tables.php
   ```

4. **Configure environment**
   - Copy `.env.example` to `.env` (if available)
   - Set `JWT_SECRET` to a secure random string
   - Update configuration in [`protected/config/config.php`](backend/protected/config/config.php:1)

5. **Start the development server**
   ```bash
   php -S localhost:8000 -t public
   ```

6. **Access the API**
   - Base URL: `http://localhost:8000`
   - Health check: `http://localhost:8000/health`

### Configuration

Edit [`protected/config/config.php`](backend/protected/config/config.php:1):

```php
return [
    'database' => [
        'driver' => 'sqlite',
        'database_path' => __DIR__ . '/../db/database.sqlite',
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'change-me-in-production',
        'issuer' => 'bizspine.local',
        'access_exp' => 900,      // 15 minutes
        'refresh_exp' => 604800,  // 7 days
    ],
];
```

## 🚀 Production deployment

After the API is deployed, apply the following so responses do not leak internal errors and browser clients can call the API from your frontend. All paths are relative to the [`backend/`](backend/) directory unless noted.

### JWT secret

- Set **`JWT_SECRET`** in a **`.env` file at** [`backend/.env`](backend/.env). The app loads it through `vlucas/phpdotenv` in [`src/Services/Config.php`](backend/src/Services/Config.php).
- Use a long, random value in production. Do not commit `.env` or reuse development secrets.
- If `JWT_SECRET` is unset, the process falls back to [`protected/config/config.php`](backend/protected/config/config.php) (development fallback only).

### CORS (`cors.allowed_origins`)

- Configure allowed browser origins in [`backend/protected/config/config.php`](backend/protected/config/config.php) under **`cors`** → **`allowed_origins`**.
- Use an explicit list, for example: `['https://your-frontend.example.com']`. Multiple frontends can each be listed.
- An empty array (`[]`) blocks cross-origin browser access until you add real origin(s). Wildcard (`*`) is discouraged, especially with credentials.

### PHP and Slim error exposure

- In [`backend/public/index.php`](backend/public/index.php), turn **off** client-visible PHP errors in production:
  - Set `ini_set('display_errors', '0');` and `ini_set('display_startup_errors', '0');` (keep `log_errors` enabled).
- Pass **`false`** as the first argument to **`$app->addErrorMiddleware(...)`** in the same file so Slim does not expose exception details in HTTP responses.

### Application debug flag

- In [`protected/config/config.php`](backend/protected/config/config.php), set **`environment.debug`** to **`false`** in production. When `true`, [`ErrorHandlerMiddleware`](backend/src/Middleware/ErrorHandlerMiddleware.php) may include extra exception detail in responses.

### Dangerous setup routes

- Keep **`ALLOW_INSECURE_SETUP`** unset or **`false`** in `.env` on any public host (it maps to `security.allow_insecure_setup` in config). Enable only locally when you need setup/system or diagnostic endpoints.

## 🧪 Testing

### Running Tests

```bash
# Run all tests
cd backend
vendor\bin\phpunit

# Run specific test file
vendor\bin\phpunit tests/Unit/StoreControllerTest.php

# Run with coverage (requires Xdebug)
vendor\bin\phpunit --coverage-html coverage
```

### Test Coverage

| Component | Tests | Assertions | Coverage |
|-----------|-------|------------|----------|
| **BookkeepingController** | 6 | 21 | 100% |
| **TestimonialController** | 8 | 21 | 100% |
| **ReviewController** | 6 | 17 | 100% |
| **OrderController** | 4 | 13 | 100% |
| **InventoryController** | 9 | 30 | 100% |
| **StoreController** | 13 | 42 | 100% |
| **ProductController** | 6 | 15+ | High |
| **AuthController** | 11+ | 20+ | High |
| **AuthMiddleware** | 3+ | 8+ | High |

### Test Structure

- **Unit Tests**: Located in [`tests/Unit/`](backend/tests/Unit/:1)
- **In-Memory Database**: Tests use SQLite `:memory:` for isolation
- **Test Mode**: Controllers support test mode to capture responses
- **Mocking**: Anonymous classes extend controllers to mock input

### Example Test Cases (Store)

1. ✅ Get all stores
2. ✅ Get store by ID (success and not found)
3. ✅ Create store with valid names (Siedlung, USA)
4. ✅ Create store with invalid name (validation)
5. ✅ Create store with missing name
6. ✅ Create duplicate store (conflict handling)
7. ✅ Update store (success and validation)
8. ✅ Update non-existent store
9. ✅ Delete store (success and not found)

### Example Test Cases (Inventory)

1. ✅ Get all inventory records
2. ✅ Get inventory by ID (success and not found)
3. ✅ Get inventory by product
4. ✅ Get inventory by store
5. ✅ Get low stock items
6. ✅ Create inventory record
7. ✅ Create inventory with missing fields (validation)
8. ✅ Update inventory record
9. ✅ Delete inventory record

### Example Test Cases (Orders)

1. ✅ Create order with inventory deduction
2. ✅ Create order with insufficient inventory (validation)
3. ✅ Get user's orders
4. ✅ Cancel order with inventory restoration

### Example Test Cases (Reviews)

1. ✅ Create verified review (user purchased product)
2. ✅ Create unverified review (user did not purchase)
3. ✅ Get published reviews only
4. ✅ Publish review
5. ✅ Delete own review
6. ✅ Cannot delete others' reviews (authorization)

### Example Test Cases (Testimonials)

1. ✅ Create testimonial with all fields
2. ✅ Create testimonial with invalid email (validation)
3. ✅ Create testimonial with invalid age range (validation)
4. ✅ Get published testimonials only
5. ✅ Get all testimonials (admin)
6. ✅ Publish testimonial
7. ✅ Unpublish testimonial
8. ✅ Delete testimonial

## 📁 Project Structure

```
BizSpine/
├── README.md                          # This file
├── LICENSE                            # MIT License
├── db_inspector.php                   # Database inspection utility
│
└── backend/
    ├── composer.json                  # Dependencies and autoloading
    ├── phpunit.xml                    # PHPUnit configuration
    ├── README.md                      # Detailed project documentation
    │
    ├── public/                        # Web-accessible directory
    │   ├── index.php                  # Application entry point
    │   └── .htaccess                  # Apache configuration
    │
    ├── src/                           # Application source code
    │   ├── Controllers/               # Request handlers
    │   │   ├── ApiController.php      # Base API controller
    │   │   ├── AuthController.php     # Authentication logic
    │   │   ├── HealthController.php   # Health check endpoint
    │   │   ├── ProductController.php  # Product CRUD operations
    │   │   ├── StoreController.php    # Store CRUD operations
    │   │   ├── InventoryController.php # Inventory CRUD operations
    │   │   ├── OrderController.php    # Order CRUD operations
    │   │   ├── ReviewController.php   # Review CRUD operations
    │   │   └── TestimonialController.php # Testimonial CRUD operations
    │   │
    │   ├── Models/                    # Data models
    │   │   ├── User.php               # User entity
    │   │   ├── Product.php            # Product entity
    │   │   ├── Store.php              # Store entity
    │   │   ├── Inventory.php          # Inventory entity
    │   │   ├── Order.php              # Order entity
    │   │   ├── OrderItem.php          # Order line item entity
    │   │   ├── ProductReview.php      # Product review entity
    │   │   └── Testimonial.php        # Testimonial entity
    │   │
    │   ├── Routes/                    # Route definitions
    │   │   └── api.php                # API route mapping
    │   │
    │   ├── Middleware/                # Request interceptors
    │   │   └── AuthMiddleware.php     # JWT authentication
    │   │
    │   ├── Exceptions/                # Custom exceptions
    │   │   └── ValidationException.php
    │   │
    │   └── Services/                  # Business services
    │       └── Database.php           # Database connection service
    │
    ├── protected/                     # Non-web-accessible files
    │   ├── config/                    # Configuration files
    │   │   ├── config.php             # Main configuration
    │   │   └── settings.php           # Additional settings
    │   │
    │   ├── db/                        # Database files
    │   │   ├── database.sqlite        # Main database
    │   │   └── database.sqlite.bak    # Backup
    │   │
    │   └── scripts/                   # Database scripts
    │       ├── init_db.php            # Initialize database
    │       ├── add_products_table.php # Add products table
    │       ├── add_stores_table.php   # Add stores table
    │       ├── add_inventory_table.php # Add inventory table
    │       ├── add_orders_table.php   # Add orders table
    │       ├── add_reviews_table.php  # Add reviews table
    │       └── add_testimonials_table.php # Add testimonials table
    │
    └── tests/                         # Test suite
        ├── bootstrap.php              # Test bootstrap
        ├── ExampleTest.php            # Example test
        └── Unit/                      # Unit tests
            ├── AuthControllerTest.php
            ├── AuthControllerExtendedTest.php
            ├── AuthMiddlewareTest.php
            ├── ProductControllerTest.php
            ├── StoreControllerTest.php
            ├── InventoryControllerTest.php
            ├── OrderControllerTest.php
            ├── ReviewControllerTest.php
            └── TestimonialControllerTest.php
```

## 🚀 Future Enhancements

### Planned Features

1. **Customer Management**
   - Customer profiles and accounts
   - Purchase history tracking
   - Loyalty program integration

2. **Advanced Authentication**
   - OAuth2 provider integration (Google, Facebook)
   - Two-factor authentication (2FA)
   - Password reset functionality

3. **Analytics & Reporting**
   - Sales analytics
   - Customer insights
   - Inventory reports
   - Inventory movement tracking
   - Review analytics and aggregate ratings

4. **API Enhancements**
   - Pagination for list endpoints
   - Filtering and sorting
   - Search functionality
   - Rate limiting

## 📄 License

This project is licensed under the MIT License. See the [`LICENSE`](LICENSE:1) file for details.

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Write tests for new functionality
4. Ensure all tests pass (`vendor\bin\phpunit`)
5. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
6. Push to the branch (`git push origin feature/AmazingFeature`)
7. Open a Pull Request

## 📞 Support

For issues, questions, or contributions, please open an issue on the project repository.

---

**BizSpine** · A [Tech Diplomacy](https://techdiplomacy.dev/) product
