<?php

declare(strict_types=1);

require_once __DIR__ . '/install_lib.php';

/**
 * Load demo storefront + admin sample data into an initialized database.
 */
function bizspine_seed_demo_database(PDO $pdo): void
{
    bizspine_ensure_optional_columns($pdo);

    $now = date('Y-m-d H:i:s');
    $passwordHash = password_hash(BIZSPINE_DEMO_PASSWORD, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    try {
        $userIds = [];
        $users = [
            ['admin@bizspine.example', 'BizSpine Admin', 'admin', 'Admin', 'User'],
            ['staff@bizspine.example', 'Staff Member', 'employee', 'Sam', 'Taylor'],
            ['alice@example.com', 'Alice Nguyen', 'customer', 'Alice', 'Nguyen'],
            ['bob@example.com', 'Bob Martinez', 'customer', 'Bob', 'Martinez'],
        ];

        $stmtUser = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, is_email_verified, first_name, last_name, created_at)
             VALUES (:email, :hash, :display, :role, 1, :first, :last, :created)'
        );

        foreach ($users as [$email, $display, $role, $first, $last]) {
            $stmtUser->execute([
                ':email' => $email,
                ':hash' => $passwordHash,
                ':display' => $display,
                ':role' => $role,
                ':first' => $first,
                ':last' => $last,
                ':created' => $now,
            ]);
            $userIds[$email] = (int) $pdo->lastInsertId();
        }

        $stores = [
            ['Downtown Studio', 'Walk-in location and local pickup.', '$', '123 Main St, Austin, TX'],
            ['Online Warehouse', 'Ships nationwide.', '$', null],
        ];
        $storeIds = [];
        $stmtStore = $pdo->prepare(
            'INSERT INTO stores (name, description, currency_symbol, address, created_at, updated_at)
             VALUES (:name, :desc, :currency, :address, :created, :created)'
        );
        foreach ($stores as [$name, $desc, $currency, $address]) {
            $stmtStore->execute([
                ':name' => $name,
                ':desc' => $desc,
                ':currency' => $currency,
                ':address' => $address,
                ':created' => $now,
            ]);
            $storeIds[$name] = (int) $pdo->lastInsertId();
        }

        $products = [
            ['Glow Daily Serum', 'Skincare', 'Vitamin C serum for everyday radiance.', 34.0],
            ['Calm Night Cream', 'Skincare', 'Rich moisturizer for overnight repair.', 42.0],
            ['Herbal Balance Soap', 'Body', 'Gentle cleansing bar with botanical oils.', 12.0],
            ['Sun Shield SPF 30', 'Skincare', 'Lightweight daily sun protection.', 28.0],
            ['Rosewater Toner', 'Skincare', 'Alcohol-free toner to refresh skin.', 18.0],
            ['Lavender Body Oil', 'Body', 'Nourishing oil for body and bath.', 24.0],
        ];
        $productIds = [];
        $stmtProduct = $pdo->prepare(
            'INSERT INTO products (name, type, description, cost, state, created_at, updated_at)
             VALUES (:name, :type, :desc, :cost, :state, :created, :created)'
        );
        foreach ($products as [$name, $type, $desc, $cost]) {
            $stmtProduct->execute([
                ':name' => $name,
                ':type' => $type,
                ':desc' => $desc,
                ':cost' => $cost,
                ':state' => 'For Sale',
                ':created' => $now,
            ]);
            $productIds[$name] = (int) $pdo->lastInsertId();
        }

        $inventoryRows = [
            ['Glow Daily Serum', 'Downtown Studio', 24, 5],
            ['Glow Daily Serum', 'Online Warehouse', 80, 10],
            ['Calm Night Cream', 'Downtown Studio', 3, 5],
            ['Calm Night Cream', 'Online Warehouse', 40, 8],
            ['Herbal Balance Soap', 'Downtown Studio', 50, 10],
            ['Herbal Balance Soap', 'Online Warehouse', 120, 20],
            ['Sun Shield SPF 30', 'Downtown Studio', 18, 6],
            ['Sun Shield SPF 30', 'Online Warehouse', 65, 12],
            ['Rosewater Toner', 'Downtown Studio', 30, 8],
            ['Rosewater Toner', 'Online Warehouse', 55, 10],
            ['Lavender Body Oil', 'Downtown Studio', 2, 4],
            ['Lavender Body Oil', 'Online Warehouse', 35, 8],
        ];
        $stmtInv = $pdo->prepare(
            'INSERT INTO inventory (product_id, store_id, quantity, min_quantity, last_restocked, created_at, updated_at)
             VALUES (:pid, :sid, :qty, :min, :restocked, :created, :created)'
        );
        foreach ($inventoryRows as [$pName, $sName, $qty, $min]) {
            $stmtInv->execute([
                ':pid' => $productIds[$pName],
                ':sid' => $storeIds[$sName],
                ':qty' => $qty,
                ':min' => $min,
                ':restocked' => $now,
                ':created' => $now,
            ]);
        }

        $pdo->prepare(
            'INSERT INTO coupons (code, discount_type, discount_value, min_purchase_amount, max_uses, is_active, description, created_at, updated_at)
             VALUES (:code, :type, :value, :min, :max, 1, :desc, :created, :created)'
        )->execute([
            ':code' => 'WELCOME10',
            ':type' => 'percentage',
            ':value' => 10,
            ':min' => 25,
            ':max' => 100,
            ':desc' => '10% off first order over $25',
            ':created' => $now,
        ]);
        $pdo->prepare(
            'INSERT INTO coupons (code, discount_type, discount_value, min_purchase_amount, is_active, description, created_at, updated_at)
             VALUES (:code, :type, :value, :min, 1, :desc, :created, :created)'
        )->execute([
            ':code' => 'SAVE5',
            ':type' => 'fixed',
            ':value' => 5,
            ':min' => 40,
            ':desc' => '$5 off orders over $40',
            ':created' => $now,
        ]);

        $orders = [
            [
                'number' => 'ORD-1001',
                'user' => 'alice@example.com',
                'store' => 'Online Warehouse',
                'status' => 'delivered',
                'items' => [
                    ['Glow Daily Serum', 2, 34.0],
                    ['Rosewater Toner', 1, 18.0],
                ],
                'shipping' => 6.0,
            ],
            [
                'number' => 'ORD-1002',
                'user' => 'bob@example.com',
                'store' => 'Downtown Studio',
                'status' => 'shipped',
                'items' => [
                    ['Calm Night Cream', 1, 42.0],
                    ['Herbal Balance Soap', 3, 12.0],
                ],
                'shipping' => 4.0,
            ],
            [
                'number' => 'ORD-1003',
                'user' => 'alice@example.com',
                'store' => 'Downtown Studio',
                'status' => 'processing',
                'items' => [
                    ['Sun Shield SPF 30', 1, 28.0],
                ],
                'shipping' => 5.0,
            ],
        ];

        $orderIds = [];
        $stmtOrder = $pdo->prepare(
            'INSERT INTO orders (user_id, store_id, order_number, order_date, fulfillment_status, shipping_address,
                subtotal, shipping_cost, total, customer_email, customer_name, created_at, updated_at)
             VALUES (:uid, :sid, :num, :date, :status, :addr, :sub, :ship, :total, :email, :name, :created, :created)'
        );
        $stmtItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal, created_at)
             VALUES (:oid, :pid, :sid, :qty, :price, :sub, :created)'
        );

        foreach ($orders as $order) {
            $subtotal = 0.0;
            foreach ($order['items'] as [, $qty, $price]) {
                $subtotal += $qty * $price;
            }
            $total = $subtotal + $order['shipping'];
            $email = $order['user'];
            $stmtOrder->execute([
                ':uid' => $userIds[$email],
                ':sid' => $storeIds[$order['store']],
                ':num' => $order['number'],
                ':date' => $now,
                ':status' => $order['status'],
                ':addr' => '123 Demo Street, Austin, TX 78701',
                ':sub' => $subtotal,
                ':ship' => $order['shipping'],
                ':total' => $total,
                ':email' => $email,
                ':name' => explode('@', $email)[0],
                ':created' => $now,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $orderIds[$order['number']] = $orderId;

            foreach ($order['items'] as [$pName, $qty, $price]) {
                $lineSub = $qty * $price;
                $stmtItem->execute([
                    ':oid' => $orderId,
                    ':pid' => $productIds[$pName],
                    ':sid' => $storeIds[$order['store']],
                    ':qty' => $qty,
                    ':price' => $price,
                    ':sub' => $lineSub,
                    ':created' => $now,
                ]);
            }

            $pdo->prepare(
                'INSERT INTO income (order_id, amount, category, payment_method, payment_date, description, created_at, updated_at)
                 VALUES (:oid, :amount, :cat, :method, :date, :desc, :created, :created)'
            )->execute([
                ':oid' => $orderId,
                ':amount' => $total,
                ':cat' => 'Sales',
                ':method' => 'card',
                ':date' => $now,
                ':desc' => 'Order ' . $order['number'],
                ':created' => $now,
            ]);
        }

        $expenses = [
            ['Supplies', 150.0, 'Packaging vendor'],
            ['Marketing', 75.0, 'Social ads'],
            ['Rent', 900.0, 'Studio lease'],
        ];
        $stmtExpense = $pdo->prepare(
            'INSERT INTO expenses (category, amount, vendor, expense_date, description, created_at, updated_at)
             VALUES (:cat, :amount, :vendor, :date, :desc, :created, :created)'
        );
        foreach ($expenses as [$cat, $amount, $vendor]) {
            $stmtExpense->execute([
                ':cat' => $cat,
                ':amount' => $amount,
                ':vendor' => $vendor,
                ':date' => $now,
                ':desc' => $vendor,
                ':created' => $now,
            ]);
        }

        $reviews = [
            ['alice@example.com', 'Glow Daily Serum', 5, 'My skin looks brighter after two weeks.', 1],
            ['bob@example.com', 'Herbal Balance Soap', 4, 'Smells amazing and not drying.', 1],
            ['alice@example.com', 'Calm Night Cream', 5, 'Waiting for moderation — very creamy.', 0],
        ];
        $stmtReview = $pdo->prepare(
            'INSERT INTO product_reviews (user_id, product_id, rating, review_text, verified, published, created_at, updated_at)
             VALUES (:uid, :pid, :rating, :text, 1, :pub, :created, :created)'
        );
        foreach ($reviews as [$email, $pName, $rating, $text, $published]) {
            $stmtReview->execute([
                ':uid' => $userIds[$email],
                ':pid' => $productIds[$pName],
                ':rating' => $rating,
                ':text' => $text,
                ':pub' => $published,
                ':created' => $now,
            ]);
        }

        $testimonials = [
            ['Jordan Lee', 'jordan@example.com', 'The serum and toner combo changed my routine.', 1],
            ['Morgan Ellis', 'morgan@example.com', 'Fast shipping and thoughtful packaging.', 1],
            ['Casey Kim', 'casey@example.com', 'Submitted recently — great lavender oil.', 0],
        ];
        $stmtTest = $pdo->prepare(
            'INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, published, created_at, updated_at)
             VALUES (:name, :email, :text, 5, :pub, :created, :created)'
        );
        foreach ($testimonials as [$name, $email, $text, $pub]) {
            $stmtTest->execute([
                ':name' => $name,
                ':email' => $email,
                ':text' => $text,
                ':pub' => $pub,
                ':created' => $now,
            ]);
        }

        $orderId = $orderIds['ORD-1001'];
        $itemId = (int) $pdo->query(
            "SELECT id FROM order_items WHERE order_id = {$orderId} LIMIT 1"
        )->fetchColumn();

        $pdo->prepare(
            'INSERT INTO returns (order_id, user_id, return_number, status, reason, refund_amount, created_at, updated_at)
             VALUES (:oid, :uid, :num, :status, :reason, :refund, :created, :created)'
        )->execute([
            ':oid' => $orderId,
            ':uid' => $userIds['alice@example.com'],
            ':num' => 'RET-DEMO-001',
            ':status' => 'requested',
            ':reason' => 'Ordered wrong shade',
            ':refund' => 34.0,
            ':created' => $now,
        ]);
        $returnId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO return_items (return_id, order_item_id, product_id, store_id, quantity, refund_amount, created_at)
             VALUES (:rid, :oiid, :pid, :sid, 1, 34.0, :created)'
        )->execute([
            ':rid' => $returnId,
            ':oiid' => $itemId,
            ':pid' => $productIds['Glow Daily Serum'],
            ':sid' => $storeIds['Online Warehouse'],
            ':created' => $now,
        ]);

        $pdo->prepare(
            'INSERT INTO user_referrals (user_id, referral_code, discount_type, discount_amount, status, created_at, updated_at)
             VALUES (:uid, :code, :type, :amount, :status, :created, :created)'
        )->execute([
            ':uid' => $userIds['alice@example.com'],
            ':code' => 'ALICE10',
            ':type' => 'percentage',
            ':amount' => 10,
            ':status' => 'active',
            ':created' => $now,
        ]);

        if (bizspine_table_exists($pdo, 'tax_rates')) {
            $pdo->prepare(
                'INSERT INTO tax_rates (name, rate, region, is_default, is_active, description, created_at, updated_at)
                 VALUES (:name, :rate, :region, 1, 1, :desc, :created, :created)'
            )->execute([
                ':name' => 'Texas Sales Tax',
                ':rate' => 8.25,
                ':region' => 'TX',
                ':desc' => 'Default state rate',
                ':created' => $now,
            ]);
        }

        if (bizspine_table_exists($pdo, 'settings')) {
            $pdo->prepare("UPDATE settings SET value = :v, updated_at = :t WHERE key = 'store_name'")
                ->execute([':v' => 'BizSpine Demo Store', ':t' => $now]);
            $pdo->prepare("UPDATE settings SET value = :v, updated_at = :t WHERE key = 'store_email'")
                ->execute([':v' => 'admin@bizspine.example', ':t' => $now]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
