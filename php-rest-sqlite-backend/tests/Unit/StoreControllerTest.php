<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\StoreController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class StoreControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetAllStores()
    {
        // Insert stores into the database
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Siedlung store location')");
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('USA', 'USA store location')");
        $controller = new StoreController();
        $request = $this->createRequest('GET', '/stores');
        $response = $this->createResponse();
        $response = $controller->getAll($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data->success);
        $this->assertCount(2, $data->data);
        $this->assertEquals('Siedlung', $data->data[0]->name);
        $this->assertEquals('USA', $data->data[1]->name);
    }

    public function testGetStoreById()
    {
        // Insert a store into the database
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Siedlung store location')");
        $id = (int)self::$db->lastInsertId();
        $controller = new StoreController();
        $request = $this->createRequest('GET', "/stores/$id");
        $response = $this->createResponse();
        $response = $controller->getById($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data->success);
        $this->assertEquals('Siedlung', $data->data->name);
        $this->assertEquals('Siedlung store location', $data->data->description);
    }

    public function testGetStoreByIdNotFound()
    {
        $controller = new StoreController();
        $request = $this->createRequest('GET', '/stores/999');
        $response = $this->createResponse();
        $response = $controller->getById($request, $response, ['id' => 999]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($data->success);
        $this->assertEquals('Store not found', $data->error);
    }

    public function testCreateStoreWithValidName()
    {
        $controller = new StoreController();
        $request = $this->createRequestWithBody('POST', '/stores', [
            'name' => 'Siedlung',
            'description' => 'Siedlung store location',
            'address' => '123 Main St',
            'phone' => '555-1234',
            'email' => 'siedlung@example.com',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data->success);
        $this->assertEquals('Siedlung', $data->data->name);
        $this->assertEquals('Siedlung store location', $data->data->description);
        $stmt = self::$db->query("SELECT COUNT(*) FROM stores WHERE name = 'Siedlung'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testCreateStoreWithInvalidName()
    {
        $controller = new StoreController();
        $request = $this->createRequestWithBody('POST', '/stores', [
            'name' => 'AB', // Too short, should trigger validation error
            'description' => 'Invalid store',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($data->success);
        $this->assertEquals('Name is required and must be at least 3 characters', $data->error);
    }

    public function testCreateStoreWithMissingName()
    {
        $controller = new StoreController();
        $request = $this->createRequestWithBody('POST', '/stores', [
            'description' => 'Store without name',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($data->success);
        $this->assertEquals('Name is required and must be at least 3 characters', $data->error);
    }

    public function testCreateDuplicateStore()
    {
        // Insert a store first
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'First Siedlung')");
        $controller = new StoreController();
        $request = $this->createRequestWithBody('POST', '/stores', [
            'name' => 'Siedlung',
            'description' => 'Duplicate Siedlung',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($data->success);
        $this->assertEquals('Store with this name already exists', $data->error);
    }

    public function testUpdateStore()
    {
        // Insert a store into the database
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Old description')");
        $id = (int)self::$db->lastInsertId();
        $controller = new StoreController();
        $request = $this->createRequestWithBody('PUT', "/stores/$id", [
            'name' => 'Siedlung',
            'description' => 'Updated description',
            'address' => '456 New St',
        ]);
        $response = $this->createResponse();
        $response = $controller->update($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data->success);
        $this->assertEquals('Siedlung', $data->data->name);
        $this->assertEquals('Updated description', $data->data->description);
        $this->assertEquals('456 New St', $data->data->address);
        $stmt = self::$db->query("SELECT description FROM stores WHERE id = $id");
        $this->assertEquals('Updated description', $stmt->fetchColumn());
    }

    public function testUpdateStoreWithInvalidName()
    {
        // Insert a store into the database
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Old description')");
        $id = (int)self::$db->lastInsertId();
        $controller = new StoreController();
        $request = $this->createRequestWithBody('PUT', "/stores/$id", [
            'name' => 'AB', // Too short
            'description' => 'Updated description',
        ]);
        $response = $this->createResponse();
        $response = $controller->update($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Name is required and must be at least 3 characters', $data->error);
    }

    public function testUpdateNonExistentStore()
    {
        $controller = new StoreController();
        $request = $this->createRequestWithBody('PUT', '/stores/999', [
            'name' => 'USA',
            'description' => 'Updated description',
        ]);
        $response = $this->createResponse();
        $response = $controller->update($request, $response, ['id' => 999]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Store not found', $data->error);
    }

    public function testDeleteStore()
    {
        // Insert a store into the database
        self::$db->exec("INSERT INTO stores (name, description) VALUES ('Siedlung', 'Store to delete')");
        $id = (int)self::$db->lastInsertId();
        $controller = new StoreController();
        $request = $this->createRequest('DELETE', "/stores/$id");
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => $id]);
        $this->assertEquals(204, $response->getStatusCode());
        $stmt = self::$db->query("SELECT COUNT(*) FROM stores WHERE id = $id");
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testDeleteNonExistentStore()
    {
        $controller = new StoreController();
        $request = $this->createRequest('DELETE', '/stores/999');
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => 999]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Store not found', $data->error);
    }

    public function testCreateUSAStore()
    {
        $controller = new StoreController();
        $request = $this->createRequestWithBody('POST', '/stores', [
            'name' => 'USA',
            'description' => 'USA store location',
            'address' => '789 American Ave',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($data->success);
        $this->assertEquals('USA', $data->data->name);
        $this->assertEquals('USA store location', $data->data->description);
    }
}
