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
        self::$db->exec("INSERT INTO users (email) VALUES ('test@example.com')");
        $this->userId = (int)self::$db->lastInsertId();
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
        $response = $controller->create($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('SAVE20', $body['code']);
        $this->assertEquals('percentage', $body['discount_type']);
        $this->assertEquals(20, $body['discount_value']);
    }

    public function testValidateCouponPercentage()
    {
        self::$db->exec("INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES ('SAVE10', 'percentage', 10, 1)");
        self::$db->exec("INSERT INTO orders (user_id, order_number, total, shipping_address) VALUES ({$this->userId}, 'ORD-001', 100.00, '123 Main St')");
        $orderId = (int)self::$db->lastInsertId();
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
        $orderId = (int)self::$db->lastInsertId();
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
}
