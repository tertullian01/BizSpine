<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\OrderController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class OrderControllerTest extends DatabaseTestCase
{
    private int $userId;
    private int $productId;
    private int $storeId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert test data
        self::$db->exec("INSERT INTO users (email, password_hash) VALUES ('test@example.com', 'hash')");
        $this->userId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Test Product', 29.99)");
        $this->productId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO stores (name) VALUES ('Siedlung')");
        $this->storeId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 100)");
    }

    public function testCreateOrder()
    {
        $controller = new OrderController(self::$db);
        $request = $this->createRequestWithBody('POST', '/orders', [
            'shipping_address' => '123 Test St, Test City',
            'phone_number' => '555-1234',
            'items' => [
                [
                    'product_id' => $this->productId,
                    'store_id' => $this->storeId,
                    'quantity' => 2
                ]
            ]
        ]);
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('123 Test St, Test City', $data['shipping_address']);
        $this->assertEquals(59.98, $data['subtotal']);
// 2 * 29.99
        $this->assertCount(1, $data['items']);
// Verify inventory was reduced
        $stmt = self::$db->query("SELECT quantity FROM inventory WHERE product_id = {$this->productId}");
        $this->assertEquals(98, $stmt->fetchColumn());
    }

    public function testCreateOrderInsufficientInventory()
    {
        $controller = new OrderController(self::$db);
        $request = $this->createRequestWithBody('POST', '/orders', [
            'shipping_address' => '123 Test St',
            'items' => [
                [
                    'product_id' => $this->productId,
                    'store_id' => $this->storeId,
                    'quantity' => 200 // More than available
                ]
            ]
        ]);
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient inventory', $data['error']);
    }

    public function testGetMyOrders()
    {
        // Create an order first
        self::$db->exec("INSERT INTO orders (user_id, order_number, shipping_address, subtotal, total) VALUES ({$this->userId}, 'ORD-001', '123 Test St', 29.99, 29.99)");
        $controller = new OrderController(self::$db);
        $request = $this->createRequest('GET', '/orders/my');
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->getMyOrders($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertEquals('ORD-001', $data[0]['order_number']);
    }

    public function testCancelOrder()
    {
        // Create order and order item
        self::$db->exec("INSERT INTO orders (user_id, order_number, shipping_address, subtotal, total, fulfillment_status) VALUES ({$this->userId}, 'ORD-002', '123 Test St', 29.99, 29.99, 'pending')");
        $orderId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal) VALUES ($orderId, {$this->productId}, {$this->storeId}, 5, 29.99, 149.95)");
// Reduce inventory
        self::$db->exec("UPDATE inventory SET quantity = 95 WHERE product_id = {$this->productId}");
        $controller = new OrderController(self::$db);
        $request = $this->createRequest('POST', "/orders/$orderId/cancel");
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->cancel($request, $response, ['id' => $orderId]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('cancelled', $data['fulfillment_status']);
// Verify inventory was restored
        $stmt = self::$db->query("SELECT quantity FROM inventory WHERE product_id = {$this->productId}");
        $this->assertEquals(100, $stmt->fetchColumn());
    }
}
