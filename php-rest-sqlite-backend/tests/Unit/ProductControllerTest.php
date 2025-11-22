<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\ProductController;
use App\Services\CacheableProductService;
use App\Services\Logger;
use App\Services\PaginationService;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ProductControllerTest extends DatabaseTestCase
{
    private CacheableProductService $cacheableProductService;
    private Logger $logger;
    private PaginationService $paginationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheableProductService = new CacheableProductService();
        $this->logger = new Logger('test', 'php://memory');
        $this->paginationService = new PaginationService();
    }

    public function testGetAllProducts()
    {
        // Insert a few products into the database
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Product 1', 10.99)");
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Product 2', 20.99)");

        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequest('GET', '/products');
        $response = $this->createResponse();

        $response = $controller->getAll($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data['success']);
        // Now returns paginated response in data
        $this->assertArrayHasKey('data', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertCount(2, $data['data']['data']);
        $this->assertEquals('Product 1', $data['data']['data'][0]['name']);
        $this->assertEquals('Product 2', $data['data']['data'][1]['name']);
    }

    public function testGetProductById()
    {
        // Insert a product into the database
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Product 1', 10.99)");
        $id = (int)self::$db->lastInsertId();

        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequest('GET', "/products/$id");
        $response = $this->createResponse();

        $response = $controller->getById($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data->success);
        $this->assertEquals('Product 1', $data->data->name);
    }

    public function testGetProductByIdNotFound()
    {
        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequest('GET', '/products/999');
        $response = $this->createResponse();

        $response = $controller->getById($request, $response, ['id' => 999]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($data['success']);
        $this->assertEquals('Product not found', $data['error']);
    }

    public function testCreateProduct()
    {
        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequestWithBody('POST', '/products', [
            'name' => 'New Product',
            'cost' => 15.99,
        ]);
        $response = $this->createResponse();

        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($data->success);
        $this->assertEquals('New Product', $data->data->name);
        $this->assertEquals(15.99, $data->data->cost);

        $stmt = self::$db->query("SELECT COUNT(*) FROM products WHERE name = 'New Product'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testUpdateProduct()
    {
        // Insert a product into the database
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Product 1', 10.99)");
        $id = (int)self::$db->lastInsertId();

        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequestWithBody('PUT', "/products/$id", [
            'name' => 'Updated Product',
            'cost' => 25.99,
        ]);
        $response = $this->createResponse();

        $response = $controller->update($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);

        $this->assertTrue($data->success);
        $this->assertEquals('Updated Product', $data->data->name);
        $this->assertEquals(25.99, $data->data->cost);

        $stmt = self::$db->query("SELECT name FROM products WHERE id = $id");
        $this->assertEquals('Updated Product', $stmt->fetchColumn());
    }

    public function testDeleteProduct()
    {
        // Insert a product into the database
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Product 1', 10.99)");
        $id = (int)self::$db->lastInsertId();

        $controller = new ProductController($this->cacheableProductService, $this->logger, $this->paginationService);
        $request = $this->createRequest('DELETE', "/products/$id");
        $response = $this->createResponse();

        $response = $controller->delete($request, $response, ['id' => $id]);

        $this->assertEquals(204, $response->getStatusCode());

        $stmt = self::$db->query("SELECT COUNT(*) FROM products WHERE id = $id");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
