<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\ReferralController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ReferralControllerTest extends DatabaseTestCase
{
    private int $userId;
    private int $referredUserId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert test users
        self::$db->exec("INSERT INTO users (email) VALUES ('referrer@example.com')");
        $this->userId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO users (email) VALUES ('referred@example.com')");
        $this->referredUserId = (int)self::$db->lastInsertId();
    }

    public function testGetMyReferralCreatesCodeIfNotExists()
    {
        $controller = new ReferralController();
        $request = $this->createRequest('GET', '/api/referrals/my')
            ->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->getMyReferral($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body->success);
        $this->assertEquals($this->userId, $body->data->user_id);
        $this->assertStringStartsWith('REF-', $body->data->referral_code);
        $this->assertEquals(0, $body->data->times_used);
        $this->assertEquals(0, $body->data->points_balance);
    }

    public function testValidateReferralCodeSuccess()
    {
        // Create referral code for user
        self::$db->exec("INSERT INTO user_referrals (user_id, referral_code) VALUES ({$this->userId}, 'REF-TEST123')");
        $controller = new ReferralController();
        $result = $controller->validateReferralCode('REF-TEST123', $this->referredUserId);
        $this->assertTrue($result);
    }

    public function testValidateReferralCodeCannotUseOwnCode()
    {
        // Create referral code for user
        self::$db->exec("INSERT INTO user_referrals (user_id, referral_code) VALUES ({$this->userId}, 'REF-TEST123')");
        $controller = new ReferralController();
        $result = $controller->validateReferralCode('REF-TEST123', $this->userId);
        $this->assertFalse($result);
    }

    public function testValidateReferralCodeCanOnlyBeUsedOnce()
    {
        // Create referral code
        self::$db->exec("INSERT INTO user_referrals (user_id, referral_code) VALUES ({$this->userId}, 'REF-TEST123')");
// Create first order and use referral
        self::$db->exec("INSERT INTO orders (user_id, order_number, shipping_address, total) VALUES ({$this->referredUserId}, 'ORD-001', '123 Main St', 50.00)");
        $controller = new ReferralController();
        $result = $controller->validateReferralCode('REF-TEST123', $this->referredUserId);
        $this->assertFalse($result);
// Should fail - already used a referral
    }

    public function testRedeemPoints()
    {
        // Create referral with points
        self::$db->exec("INSERT INTO user_referrals (user_id, referral_code, points_balance) VALUES ({$this->userId}, 'REF-TEST123', 200)");
        $controller = new ReferralController();
        $requestData = ['points' => 50];
        $request = $this->createRequestWithBody('POST', '/api/referrals/redeem', $requestData)
            ->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->redeemPoints($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body->success);
        $this->assertEquals(50, $body->data->points_redeemed);
        $this->assertEquals(150, $body->data->new_balance);
// Verify database was updated
        $stmt = self::$db->query("SELECT points_balance, points_redeemed FROM user_referrals WHERE user_id = {$this->userId}");
        $referral = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(150, $referral['points_balance']);
        $this->assertEquals(50, $referral['points_redeemed']);
    }

    public function testRedeemPointsInsufficientBalance()
    {
        // Create referral with low points
        self::$db->exec("INSERT INTO user_referrals (user_id, referral_code, points_balance) VALUES ({$this->userId}, 'REF-TEST123', 30)");
        $controller = new ReferralController();
        $requestData = ['points' => 50];
        $request = $this->createRequestWithBody('POST', '/api/referrals/redeem', $requestData)
            ->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->redeemPoints($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Insufficient points balance', $body->error);
    }
}
