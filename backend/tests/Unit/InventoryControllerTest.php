<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\InventoryController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class InventoryControllerTest extends DatabaseTestCase
{
    private int $productId;
    private int $storeId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert test product
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Test Product', 10.99)");
        $this->productId = (int)self::$db->lastInsertId();
    // Insert test store
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Test Store')");
        $this->storeId = (int)self::$db->lastInsertId();
    }

    public function testGetAllInventory()
    {
        // Insert inventory records
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity, min_quantity) VALUES ({$this->productId}, {$this->storeId}, 100, 10)");
        $controller = new InventoryController();
        $request = $this->createRequest('GET', '/inventory');
        $response = $this->createResponse();
        $response = $controller->getAll($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['data']);
        $this->assertEquals(100, $data['data']['data'][0]['quantity']);
        $this->assertEquals('Test Product', $data['data']['data'][0]['product_name']);
        $this->assertEquals('Siedlung', $data['data']['data'][0]['store_name']);
    }

    public function testGetInventoryById()
    {
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 50)");
        $id = (int)self::$db->lastInsertId();
        $controller = new InventoryController();
        $request = $this->createRequest('GET', "/inventory/$id");
        $response = $this->createResponse();
        $response = $controller->getById($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals(50, $data['data']['quantity']);
        $this->assertEquals($this->productId, $data['data']['product_id']);
        $this->assertEquals($this->storeId, $data['data']['store_id']);
    }

    public function testGetInventoryByIdNotFound()
    {
        $controller = new InventoryController();
        $request = $this->createRequest('GET', '/inventory/999');
        $response = $this->createResponse();
        $response = $controller->getById($request, $response, ['id' => 999]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Inventory record not found', $data['error']);
    }

    public function testGetInventoryByProduct()
    {
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 75)");
        $controller = new InventoryController();
        $request = $this->createRequest('GET', "/inventory/product/{$this->productId}");
        $response = $this->createResponse();
        $response = $controller->getByProduct($request, $response, ['id' => $this->productId]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals(75, $data['data'][0]['quantity']);
    }

    public function testGetInventoryByStore()
    {
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 60)");
        $controller = new InventoryController();
        $request = $this->createRequest('GET', "/inventory/store/{$this->storeId}");
        $response = $this->createResponse();
        $response = $controller->getByStore($request, $response, ['id' => $this->storeId]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals(60, $data['data'][0]['quantity']);
    }

    public function testGetLowStock()
    {
        // Insert low stock item
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity, min_quantity) VALUES ({$this->productId}, {$this->storeId}, 5, 10)");
        $controller = new InventoryController();
        $request = $this->createRequest('GET', '/inventory/low-stock');
        $response = $this->createResponse();
        $response = $controller->getLowStock($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals(5, $data['data'][0]['quantity']);
        $this->assertEquals(10, $data['data'][0]['min_quantity']);
    }

    public function testCreateInventory()
    {
        $controller = new InventoryController();
        $request = $this->createRequestWithBody('POST', '/inventory', [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'quantity' => 100,
            'min_quantity' => 20,
            'max_quantity' => 500,
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals(100, $data['data']['quantity']);
        $this->assertEquals(20, $data['data']['min_quantity']);
        $this->assertEquals(500, $data['data']['max_quantity']);
    }

    public function testCreateInventoryMissingFields()
    {
        $controller = new InventoryController();
        $request = $this->createRequestWithBody('POST', '/inventory', [
            'quantity' => 100,
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('product_id and store_id are required', $data['error']);
    }

    public function testDeleteInventory()
    {
        self::$db->exec("INSERT INTO inventory (product_id, store_id, quantity) VALUES ({$this->productId}, {$this->storeId}, 50)");
        $id = (int)self::$db->lastInsertId();
        $controller = new InventoryController();
        $request = $this->createRequest('DELETE', "/inventory/$id");
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => $id]);
        $this->assertEquals(204, $response->getStatusCode());
        $stmt = self::$db->query("SELECT COUNT(*) FROM inventory WHERE id = $id");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
