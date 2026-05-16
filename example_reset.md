BizSpine Example Database Reset
================================

This document explains how to use example_reset.php to wipe the local SQLite
database and load demo data for the storefront and administration UI.


Prerequisites
-------------

1. PHP 8+ on your PATH
2. Composer dependencies installed for the backend:

   cd backend
   composer install

3. Run all commands from the project root (the folder that contains
   example_reset.php).


Full reset (recommended)
------------------------

Deletes the database file, runs migrations, and seeds sample data:

   php example_reset.php

Use this when you want a clean slate or after pulling schema changes.


Clear data only
---------------

Keeps the existing database file and schema; deletes all rows and re-seeds:

   php example_reset.php --no-migrate

Use this when the schema is already up to date and you only want fresh demo
data.


What the script does
--------------------

1. Loads backend/protected/config/config.php for the database path
2. (Default mode) Deletes backend/protected/db/database.sqlite
3. (Default mode) Runs Phinx migrations to recreate tables and default settings
4. Adds optional user profile columns if they are missing on a fresh install
5. Inserts demo users, stores, products, inventory, orders, coupons, reviews,
   testimonials, bookkeeping records, a return request, and related data


Demo accounts
-------------

Every demo account uses the same password:

   Password: Example123!

   Email                      Role       Use for
   -------------------------  ---------  ----------------------------------
   admin@bizspine.example     admin      Full admin dashboard (/admin)
   staff@bizspine.example     employee   Staff dashboard (no admin-only pages)
   alice@example.com          customer   Storefront customer account
   bob@example.com            customer   Storefront customer account


Trying the app after reset
--------------------------

1. Start the API (from backend/), for example:

   php -S localhost:8000 -t public

2. Start the frontend (from frontend/), for example:

   npm run dev

3. Open the storefront in your browser (Vite dev server URL, often
   http://localhost:5173).

4. Sign in on the Account page, or go to /admin and sign in as
   admin@bizspine.example with password Example123!.


Sample data overview
--------------------

- 2 stores (Downtown Studio, Online Warehouse)
- 6 skincare/body products with inventory (includes low-stock rows)
- 3 orders with different fulfillment statuses
- 2 coupons: WELCOME10 (10% off), SAVE5 ($5 off)
- Published and pending product reviews
- Published and draft testimonials
- 1 return request (status: requested) for testing Returns in admin
- Bookkeeping income/expenses, referral code for Alice, Texas tax rate
- Store settings updated to "BizSpine Demo Store"


Coupons (for checkout testing)
------------------------------

   WELCOME10   10% off orders over $25
   SAVE5       $5 off orders over $40


Troubleshooting
---------------

"Composer autoload not found"
   Run: cd backend && composer install

Migration or seed errors
   Run the full reset again: php example_reset.php

Cannot sign in after reset
   Confirm the API is using the same database path as in
   backend/protected/config/config.php (default:
   backend/protected/db/database.sqlite).
