<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\BookkeepingController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class BookkeepingControllerTest extends DatabaseTestCase
{
    private int $orderId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert a user for the foreign key constraint
        self::$db->exec("INSERT INTO users (id, email) VALUES (1, 'test@user.com')");
    // Insert test order
        self::$db->exec("INSERT INTO orders (user_id, order_number, shipping_address, total) VALUES (1, 'ORD-001', '123 Test St', 100.00)");
        $this->orderId = (int)self::$db->lastInsertId();
    }

    public function testCreateIncome()
    {
        $controller = new BookkeepingController(self::$db);
        $requestData = [
            'order_id' => $this->orderId,
            'amount' => 100.00,
            'payment_method' => 'Credit Card',
            'description' => 'Payment for order',
        ];
        $request = $this->createRequestWithBody('POST', '/api/income', $requestData);
        $response = $this->createResponse();
        $response = $controller->createIncome($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(100.00, $body['amount']);
        $this->assertEquals('Credit Card', $body['payment_method']);
    }

    public function testCreateExpense()
    {
        $controller = new BookkeepingController(self::$db);
        $requestData = [
            'vendor' => 'Office Supplies Inc',
            'category' => 'Supplies',
            'amount' => 50.00,
            'description' => 'Office supplies purchase',
            'receipt_image_url' => 'https://example.com/receipt.jpg',
        ];
        $request = $this->createRequestWithBody('POST', '/api/expenses', $requestData);
        $response = $this->createResponse();
        $response = $controller->createExpense($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Office Supplies Inc', $body['vendor']);
        $this->assertEquals('Supplies', $body['category']);
        $this->assertEquals(50.00, $body['amount']);
    }

    public function testGetAllIncome()
    {
        self::$db->exec("INSERT INTO income (order_id, amount, payment_method) VALUES ({$this->orderId}, 100.00, 'Cash')");
        $controller = new BookkeepingController(self::$db);
        $request = $this->createRequest('GET', '/api/income');
        $response = $this->createResponse();
        $response = $controller->getAllIncome($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $body);
        $this->assertEquals(100.00, $body[0]['amount']);
    }

    public function testGetAllExpenses()
    {
        self::$db->exec("INSERT INTO expenses (category, amount, vendor) VALUES ('Shipping', 15.00, 'UPS')");
        $controller = new BookkeepingController(self::$db);
        $request = $this->createRequest('GET', '/api/expenses');
        $response = $this->createResponse();
        $response = $controller->getAllExpenses($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $body);
        $this->assertEquals(15.00, $body[0]['amount']);
        $this->assertEquals('Shipping', $body[0]['category']);
    }

    public function testGetSummary()
    {
        // Insert income and expenses
        self::$db->exec("INSERT INTO income (amount) VALUES (500.00)");
        self::$db->exec("INSERT INTO income (amount) VALUES (300.00)");
        self::$db->exec("INSERT INTO expenses (category, amount) VALUES ('Supplies', 100.00)");
        self::$db->exec("INSERT INTO expenses (category, amount) VALUES ('Shipping', 50.00)");
        $controller = new BookkeepingController(self::$db);
        $request = $this->createRequest('GET', '/api/bookkeeping/summary');
        $response = $this->createResponse();
        $response = $controller->getSummary($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(800.00, $body['total_income']);
        $this->assertEquals(150.00, $body['total_expenses']);
        $this->assertEquals(650.00, $body['profit']);
        $this->assertCount(2, $body['expenses_by_category']);
    }

    public function testDeleteExpense()
    {
        self::$db->exec("INSERT INTO expenses (category, amount) VALUES ('Supplies', 25.00)");
        $id = (int)self::$db->lastInsertId();
        $controller = new BookkeepingController(self::$db);
        $request = $this->createRequest('DELETE', "/api/expenses/{$id}");
        $response = $this->createResponse();
        $response = $controller->deleteExpense($request, $response, ['id' => $id]);
        $this->assertEquals(204, $response->getStatusCode());
        $stmt = self::$db->query("SELECT COUNT(*) FROM expenses WHERE id = $id");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
