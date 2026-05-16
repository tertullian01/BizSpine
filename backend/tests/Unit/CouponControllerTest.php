<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\CouponController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class CouponControllerTest extends DatabaseTestCase
{
    private int $userId;
    protected function setUp(): void
    {
        parent::setUp();

        // Create coupons table manually as it's not in Phinx migrations yet
        self::$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  discount_type TEXT NOT NULL,
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
CREATE TABLE IF NOT EXISTS coupon_usage (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  coupon_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  order_id INTEGER NOT NULL,
  discount_amount REAL NOT NULL,
  used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
);
SQL
        );

        self::$db->exec("INSERT INTO users (email) VALUES ('test@example.com')");
        $this->userId = (int) self::$db->lastInsertId();
    }

    public function testCreateCoupon()
    {
        $controller = new CouponController(self::$db);
        $requestData = [
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_purchase_amount' => 50,
            'description' => '20% off orders over $50',
        ];
        $request = $this->createRequestWithBody('POST', '/api/coupons', $requestData);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('SAVE20', $body['data']['code']);
        $this->assertEquals('percentage', $body['data']['discount_type']);
        $this->assertEquals(20, $body['data']['discount_value']);
    }

    public function testValidateCouponPercentage()
    {
        self::$db->exec("INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES ('SAVE10', 'percentage', 10, 1)");
        self::$db->exec("INSERT INTO orders (user_id, order_number, total, shipping_address) VALUES ({$this->userId}, 'ORD-001', 100.00, '123 Main St')");
        $orderId = (int) self::$db->lastInsertId();
        $controller = new CouponController(self::$db);
        $result = $controller->validateCoupon('SAVE10', 100.00, $this->userId, $orderId);
        $this->assertTrue($result['valid']);
        $this->assertEquals(10.00, $result['discount_amount']);
        // 10% of 100

        // Verify usage was recorded
        $stmt = self::$db->query("SELECT COUNT(*) FROM coupon_usage WHERE user_id = {$this->userId}");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testValidateCouponFixed()
    {
        self::$db->exec("INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES ('SAVE15', 'fixed', 15, 1)");
        self::$db->exec("INSERT INTO orders (user_id, order_number, total, shipping_address) VALUES ({$this->userId}, 'ORD-001', 100.00, '123 Main St')");
        $orderId = (int) self::$db->lastInsertId();
        $controller = new CouponController(self::$db);
        $result = $controller->validateCoupon('SAVE15', 100.00, $this->userId, $orderId);
        $this->assertTrue($result['valid']);
        $this->assertEquals(15.00, $result['discount_amount']);
    }

    public function testValidateCouponMinPurchase()
    {
        self::$db->exec("INSERT INTO coupons (code, discount_type, discount_value, min_purchase_amount, is_active) VALUES ('SAVE20', 'fixed', 20, 100, 1)");
        $controller = new CouponController(self::$db);
        $result = $controller->validateCoupon('SAVE20', 50.00, $this->userId, 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum purchase', $result['error']);
    }

    public function testValidateCouponMaxUses()
    {
        self::$db->exec("INSERT INTO coupons (code, discount_type, discount_value, max_uses, times_used, is_active) VALUES ('LIMITED', 'fixed', 10, 5, 5, 1)");
        $controller = new CouponController(self::$db);
        $result = $controller->validateCoupon('LIMITED', 100.00, $this->userId, 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit', $result['error']);
    }
    public function testCreateCouponWithLegacyPayload()
    {
        $controller = new CouponController(self::$db);
        $requestData = [
            "code" => "atest",
            "discount_type" => "percent",
            "discount_value" => 15,
            "expiration_date" => "",
            "active" => true
        ];
        $request = $this->createRequestWithBody('POST', '/api/coupons', $requestData);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);

        if ($response->getStatusCode() !== 201) {
            file_put_contents(__DIR__ . '/../../test_error_output.txt', $response->getBody()->__toString());
        }

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('ATEST', $body['data']['code']); // Code is uppercased in controller
        $this->assertEquals('percentage', $body['data']['discount_type']); // Mapped from percent
        $this->assertEquals(15, $body['data']['discount_value']);
        $this->assertNull($body['data']['valid_until']); // Mapped from empty string expiration_date
        $this->assertEquals(1, $body['data']['is_active']); // Mapped from active=true
    }
}
