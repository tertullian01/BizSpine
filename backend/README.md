# PHP REST SQLite Backend

## Overview

A comprehensive REST API backend for small business management built with **Slim Framework 4.0** and **SQLite**. This project provides a complete e-commerce and business management solution with user authentication, product management, order processing, inventory tracking, and more.

## Features

### Core Functionality
- **User Authentication & Authorization** - JWT-based auth with registration, login, logout, and token refresh
- **Password Recovery** - Secure email-based password reset functionality
- **Product Management** - Complete product catalog with categories, pricing, and descriptions
- **Order Processing** - Full order lifecycle from creation to fulfillment
- **Inventory Management** - Real-time stock tracking across multiple store locations
- **Customer Reviews** - Product reviews and ratings system
- **Testimonials** - Customer testimonial management
- **Coupon System** - Discount codes with flexible rules and usage tracking
- **Referral Program** - User referral system with points and rewards
- **Tax Management** - Configurable tax rates by region
- **Return & Refund Processing** - Complete return workflow with inventory restoration
- **Financial Reporting** - Income/expense tracking and profit analysis
- **Multi-Store Support** - Manage multiple physical locations
- **Employee Management** - Role-based access control for employees and admins
- **User Profiles** - Detailed user profiles with address and social media links

### Technical Features
- **RESTful API Design** - Clean, consistent API endpoints
- **JWT Authentication** - Secure token-based authentication
- **Email Integration** - PHPMailer-based email sending for notifications
- **Database Migrations** - Phinx-based database versioning
- **Comprehensive Testing** - Unit and integration tests with PHPUnit
- **OpenAPI Specification** - Complete API documentation
- **Dependency Injection** - Clean architecture with service containers
- **Middleware Support** - Security headers, file uploads, metrics
- **Error Handling** - Structured error responses and logging

## Project Structure

```
php-rest-sqlite-backend/
├── public/
│   ├── index.php              # Application entry point
│   ├── .htaccess              # URL rewriting and security
│   ├── docs/                  # API documentation (Swagger UI)
│   │   ├── index.html         # Interactive API documentation
│   │   ├── CODE_QUALITY.md    # Code quality guidelines
│   │   └── README.md          # Documentation README
│   └── openapi.yaml           # OpenAPI 3.0 specification
├── src/
│   ├── Controllers/           # API endpoint controllers
│   │   ├── ApiController.php      # Base controller with common methods
│   │   ├── AuthController.php     # Authentication endpoints
│   │   ├── HealthController.php   # Health check endpoint
│   │   ├── ProductController.php  # Product management
│   │   ├── OrderController.php    # Order processing
│   │   ├── StoreController.php    # Store location management
│   │   ├── InventoryController.php # Stock management
│   │   ├── ReviewController.php    # Product reviews
│   │   ├── TestimonialController.php # Customer testimonials
│   │   ├── CouponController.php     # Discount management
│   │   ├── ReferralController.php   # Referral program
│   │   ├── TaxController.php        # Tax rate configuration
│   │   ├── ReturnController.php     # Return processing
│   │   └── BookkeepingController.php # Financial records
│   ├── Models/                 # Data models and database interactions
│   │   ├── BaseModel.php           # Base model with common DB methods
│   │   ├── User.php                # User model
│   │   ├── Product.php             # Product model
│   │   ├── Order.php               # Order model
│   │   └── ...                    # Additional models
│   ├── Routes/                 # Route definitions
│   │   ├── api.php                # Main API routes
│   │   ├── AuthRoutes.php         # Authentication routes
│   │   ├── ProductRoutes.php      # Product routes
│   │   └── ...                    # Feature-specific routes
│   ├── Services/               # Business logic and utilities
│   │   ├── Config.php             # Configuration management
│   │   ├── Database.php           # Database connection
│   │   ├── EmailService.php       # Email sending service
│   │   ├── Logger.php             # Logging service
│   │   ├── Validator.php          # Input validation
│   │   ├── Container.php          # Dependency injection
│   │   └── ...                    # Additional services
│   ├── Middleware/             # PSR-15 middleware
│   │   ├── AuthMiddleware.php     # JWT authentication
│   │   ├── CorsMiddleware.php     # Cross-origin resource sharing
│   │   ├── SecurityHeadersMiddleware.php # Security headers
│   │   ├── FileUploadMiddleware.php # File upload handling
│   │   ├── ErrorHandlerMiddleware.php # Error handling
│   │   └── MetricsMiddleware.php  # Performance metrics
│   └── Exceptions/             # Custom exceptions
│       └── ValidationException.php # Validation errors
├── protected/
│   ├── config/
│   │   ├── config.php            # Main configuration
│   │   └── settings.php          # Additional settings
│   ├── database/                # SQLite databases
│   │   ├── database.sqlite       # Production database
│   │   └── testing.db            # Test database
│   └── scripts/                 # Database setup scripts
│       ├── init_db.php           # Initial database setup
│       ├── add_user_roles.php    # Add user roles
│       └── add_password_reset.php # Password reset setup
├── tests/
│   ├── Unit/                    # Unit tests
│   │   ├── AuthControllerTest.php   # Authentication tests
│   │   ├── EmailServiceTest.php     # Email service tests
│   │   └── ...                      # Additional unit tests
│   ├── Integration/            # Integration tests
│   │   ├── PasswordResetTest.php    # Password reset integration
│   │   └── ProductApiTest.php       # Product API tests
│   ├── DatabaseTestCase.php     # Base test case with DB setup
│   └── bootstrap.php            # Test bootstrap
├── composer.json               # PHP dependencies
├── composer.lock               # Dependency lock file
├── phpunit.xml                 # PHPUnit configuration
├── phinx.php                   # Database migration config
├── .env                        # Environment variables
├── .env.example                # Environment template
├── .gitignore                  # Git ignore rules
├── LICENSE                     # MIT License
└── README.md                   # This file
```

## Installation

### Prerequisites
- PHP 7.4 or higher
- Composer
- SQLite3

### Setup Steps

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd php-rest-sqlite-backend
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Environment configuration:**
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

4. **Initial Setup Script:**
   ```bash
   # Initialize database
   php protected/scripts/init_db.php
   ```

   **Additional Setup Scripts:**
   ```bash
   # Add user roles
   php protected/scripts/add_user_roles.php

   # Add password reset functionality
   php protected/scripts/add_password_reset.php
   ```

   **Server Update Utility:**
   To run all update scripts (useful for deployments):
   ```bash
   composer db:update
   ```

5. **Run database migrations:**
   ```bash
   ./vendor/bin/phinx migrate
   ```

## Configuration

### Environment Variables (.env)
```env
# Database
DB_DATABASE=/path/to/database.sqlite

# JWT Authentication
JWT_SECRET=your-super-secret-jwt-key-here

# Email Configuration (for password reset)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
FROM_EMAIL=noreply@yourdomain.com
FROM_NAME=Your App Name
```

### Application Config (protected/config/config.php)
Contains database settings, JWT configuration, email settings, CORS settings, file upload limits, and other application parameters.

### CORS Configuration
Configure cross-origin resource sharing in `config.php`:
```php
'cors' => [
    'allowed_origins' => ['https://yourfrontend.com', 'https://test.yourfrontend.com'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'allow_credentials' => true,
],
```

**Shared Hosting Note**: If you cannot restart your web server, CORS headers are also set via `.htaccess` and PHP fallbacks in `public/index.php` for immediate compatibility.

## Usage

### Starting the Development Server
```bash
php -S localhost:8000 -t public
```

The API will be available at `http://localhost:8000`

### API Documentation
- **OpenAPI Spec**: Available at `/openapi.yaml` for complete API documentation
- **Interactive Docs**: Access `/docs/` for Swagger UI documentation

### Authentication Flow
1. **Register**: `POST /auth/register` with email/password
2. **Login**: `POST /auth/login` to get JWT token
3. **Use API**: Include `Authorization: Bearer <token>` header
4. **Password Reset**: `POST /auth/forgot-password` then `POST /auth/reset-password`

## Testing

### Running Tests
```bash
# Run all tests
composer test

# Run with code coverage
composer test:coverage
```
The coverage report will be generated in the `coverage-report/` directory. Open `index.html` to view it.

## Database Management

### Running Migrations

**Web Interface:**
Navigate to `/setup.html` in your browser. If your database is already initialized, a dedicated **Update Database Schema** tool will allow you to safely run pending migrations without losing data.

**Command Line (CLI):**
Alternatively, run Phinx migrations from your terminal:
```bash
./vendor/bin/phinx migrate
```

### Test Structure
- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test complete workflows and database interactions
- **Database Tests**: Use separate test database with automatic cleanup

## API Endpoints

### Authentication
- `POST /auth/register` - User registration
- `POST /auth/login` - User login
- `POST /auth/logout` - User logout
- `POST /auth/refresh` - Refresh access token
- `POST /auth/forgot-password` - Request password reset
- `POST /auth/reset-password` - Reset password with token

### Products & Inventory
- `GET/POST /products` - Product catalog management
- `GET/PUT/DELETE /products/{id}` - Individual product operations
- `GET/POST /inventory` - Inventory management
- `GET/PUT/DELETE /inventory/{id}` - Inventory item operations

### Orders & Sales
- `GET/POST /orders` - Order management
- `GET /orders/my` - User's orders
- `POST /orders/{id}/cancel` - Cancel order

### Users & Clients
- `GET /users` - Get all users (admin)
- `GET /users/customers` - Get all customers
- `GET /users/{id}` - Get user by ID
- `PUT /users/{id}/password` - Update user password (admin)
- `GET /clients` - Get all clients with full profile data and order statistics

### Employee Management
- `GET/POST /employees` - Manage employees
- `PUT/DELETE /employees/{id}` - Update/Delete employees

### Referrals
- `GET /referrals` - List all referrals (Admin)
- `POST /referrals` - Create a referral code (Admin)
- `GET /referrals/code/{code}` - Get referral details by code
- `GET /referrals/my` - Get user's referral code and stats
- `GET /referrals/my/usage` - Get user's referral usage history
- `GET /referrals/{id}/usage` - Get usage history for a specific referral (Admin)
- `POST /referrals/usage` - Manually add referral usage (Admin)
- `POST /referrals/redemption` - Manually redeem points (Admin)
- `POST /referrals/redeem` - Redeem points (User)
- `GET /referrals/{id}` - Get referral by ID
- `PUT/DELETE /referrals/{id}` - Update/Delete referral

### Email Logs
- `GET /email-logs` - Get all email logs (Admin)

### Additional Features
- **Stores**: Multi-location management
- **Reviews**: Product reviews and ratings
- **Coupons**: Discount code system
- **Referrals**: User referral program
- **Returns**: Return and refund processing
- **Tax Rates**: Tax configuration
- **Bookkeeping**: Financial reporting

## Development

### Code Quality
```bash
# Run code analysis
composer stan    # PHPStan static analysis
composer cs       # CodeSniffer style check
composer qa       # Run all quality checks
```

### Database Migrations
```bash
# Create new migration
./vendor/bin/phinx create MyMigration

# Run migrations
./vendor/bin/phinx migrate

# Rollback
./vendor/bin/phinx rollback
```

## Production deployment

After deployment, apply the following so the API does not leak secrets or internal errors, and browsers can reach it from your frontend.

### JWT secret

- Set **`JWT_SECRET`** in a `.env` file at the project root (`php-rest-sqlite-backend/.env`). The application loads it via `vlucas/phpdotenv` in `src/Services/Config.php` and uses it for signing JWTs.
- Use a long, random value in production. Do not commit `.env` or reuse development secrets.
- If `JWT_SECRET` is unset, the app falls back to the value in `protected/config/config.php` (development fallback only).

### CORS (`cors.allowed_origins`)

- Configure allowed browser origins in **`protected/config/config.php`** under `cors` → **`allowed_origins`**.
- Use an explicit list, for example: `['https://your-frontend.example.com']`. Multiple frontends can each be listed.
- An empty array (`[]`) is secure by default (no cross-origin `Access-Control-Allow-Origin` for arbitrary sites) but **will block** browser-based SPAs until you add your real origin(s). Wildcard (`*`) is discouraged, especially with credentials.

### PHP and Slim error exposure

- In **`public/index.php`**, turn **off** client-visible PHP errors in production:
  - Set `ini_set('display_errors', '0');` and `ini_set('display_startup_errors', '0');` (and keep `log_errors` enabled so issues still go to your log file).
- Pass **`false`** as the first argument to **`$app->addErrorMiddleware(...)`** in the same file so Slim does not expose exception details in HTTP responses. Keep logging arguments as needed for the server log.

### Application debug flag

- In **`protected/config/config.php`**, set **`environment.debug`** to **`false`** in production. When `true`, `ErrorHandlerMiddleware` may include extra exception detail in responses.

### Dangerous setup routes

- Keep **`ALLOW_INSECURE_SETUP`** unset or **`false`** in `.env` on any public host (it maps to `security.allow_insecure_setup` in config). Only enable locally when you truly need setup/system routes or diagnostic endpoints documented elsewhere.

## Security Features

- **JWT Authentication** with configurable expiration
- **Password Hashing** using PHP's password_hash()
- **SQL Injection Protection** via PDO prepared statements
- **XSS Protection** with security headers middleware
- **CSRF Protection** through JWT tokens
- **Input Validation** with Respect/Validation
- **Secure File Uploads** with type and size validation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

This project is licensed under the MIT License. See the LICENSE file for more details.

## Support

For API documentation, see the OpenAPI specification at `/openapi.yaml` or interactive docs at `/docs/`.
For issues and questions, please create an issue in the repository.