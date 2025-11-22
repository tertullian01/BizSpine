<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\ReturnController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ReturnControllerTest extends DatabaseTestCase
{
    private int $userId;
    private int $orderId;
    private int $orderItemId;
    private int $productId;
    private int $storeId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert test data
        self::$db->exec("INSERT INTO users (email) VALUES ('test@example.com')");
        $this->userId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Test Product', 50.00)");
        $this->productId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO stores (name) VALUES ('Siedlung')");
        $this->storeId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 50)");
        self::$db->exec("INSERT INTO orders (user_id, order_number, fulfillment_status, total, shipping_address) VALUES ({$this->userId}, 'ORD-001', 'delivered', 100.00, '123 Main St')");
        $this->orderId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal) VALUES ({$this->orderId}, {$this->productId}, {$this->storeId}, 2, 50.00, 100.00)");
        $this->orderItemId = (int)self::$db->lastInsertId();
    }

    public function testCreateReturn()
    {
        $controller = new ReturnController(self::$db);
        $requestData = [
            'order_id' => $this->orderId,
            'reason' => 'Product defective',
            'items' => [
                [
                    'order_item_id' => $this->orderItemId,
                    'quantity' => 1,
                    'reason' => 'Defective item',
                ]
            ]
        ];
        $request = $this->createRequestWithBody('POST', '/api/returns', $requestData)
            ->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->create($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('requested', $body['data']['status']);
        $this->assertEquals(50.00, $body['data']['refund_amount']);
        $this->assertStringStartsWith('RET-', $body['data']['return_number']);
    }

    public function testApproveReturn()
    {
        // Create a return first
        self::$db->exec("INSERT INTO returns (order_id, user_id, return_number, status, refund_amount) VALUES ({$this->orderId}, {$this->userId}, 'RET-001', 'requested', 50.00)");
        $returnId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO return_items (return_id, order_item_id, product_id, store_id, quantity, refund_amount) VALUES ($returnId, {$this->orderItemId}, {$this->productId}, {$this->storeId}, 1, 50.00)");
        $controller = new ReturnController(self::$db);
        $request = $this->createRequest('POST', "/api/returns/{$returnId}/approve");
        $response = $this->createResponse();
        $response = $controller->approve($request, $response, ['id' => $returnId]);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('approved', $body['data']['status']);
// Verify inventory was restored
        $stmt = self::$db->query("SELECT quantity FROM inventory WHERE product_id = {$this->productId}");
        $this->assertEquals(51, $stmt->fetchColumn());
// 50 + 1 returned
    }

    public function testProcessRefund()
    {
        // Create an approved return
        self::$db->exec("INSERT INTO returns (order_id, user_id, return_number, status, refund_amount) VALUES ({$this->orderId}, {$this->userId}, 'RET-002', 'approved', 50.00)");
        $returnId = (int)self::$db->lastInsertId();
        $controller = new ReturnController(self::$db);
        $requestData = [
            'refund_method' => 'Credit Card',
            'notes' => 'Refund processed',
        ];
        $request = $this->createRequestWithBody('POST', "/api/returns/{$returnId}/refund", $requestData)
            ->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->processRefund($request, $response, ['id' => $returnId]);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('completed', $body['data']['status']);
        $this->assertEquals('Credit Card', $body['data']['refund_method']);
// Verify expense was created
        $stmt = self::$db->query("SELECT COUNT(*) FROM expenses WHERE category = 'Refund'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }
}
