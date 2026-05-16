<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Config;
use App\Services\Database;
use App\Models\BaseModel;
use App\Services\Container;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class ProductApiTest extends TestCase
{
    private $app;
    private $db;

    protected function setUp(): void
    {
        // Initialize config and database
        $dbPath = Config::get('database.database_path');
        $this->db = Database::get($dbPath);
        BaseModel::setDatabase($this->db);

        // Create container and app
        $container = new Container();
        AppFactory::setContainer($container);

        // Initialize services
        $container->bind(\App\Services\Validator::class, fn($c) => new \App\Services\Validator());
        $container->bind(\App\Services\CacheableProductService::class, fn($c) => new \App\Services\CacheableProductService());
        $container->bind(\App\Services\Logger::class, fn($c) => new \App\Services\Logger('test', 'php://memory'));
        $container->bind(\App\Services\DatabasePool::class, fn($c) => new \App\Services\DatabasePool('sqlite:' . $dbPath, 5));
        $container->bind(\App\Services\Metrics::class, fn($c) => new \App\Services\Metrics($c->get(\App\Services\Logger::class)));
        $container->bind(\App\Middleware\AuthMiddleware::class, fn($c) => new \App\Middleware\AuthMiddleware('test-secret'));

        $this->app = AppFactory::create();

        // Add middleware
        $this->app->add(new \App\Middleware\MetricsMiddleware($container->get(\App\Services\Metrics::class)));
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(true, true, true);
        $this->app->add(new \App\Middleware\SecurityHeadersMiddleware([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000',
        ]));

        // Load only product routes for testing
        $app = $this->app;
        \App\Routes\ProductRoutes::register($app);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->db->exec('DELETE FROM products');
    }

    private function createRequest(string $method, string $uri, array $data = [], bool $authenticated = false): \Psr\Http\Message\RequestInterface
    {
        $requestFactory = new RequestFactory();
        $streamFactory = new StreamFactory();

        $request = $requestFactory->createRequest($method, $uri);

        if (!empty($data)) {
            $body = $streamFactory->createStream(json_encode($data));
            $request = $request->withBody($body);
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        if ($authenticated) {
            // Generate a real JWT token for testing
            $payload = [
                'iss' => 'test',
                'iat' => time(),
                'exp' => time() + 3600,
                'sub' => '1',
                'role' => 'admin',
            ];
            $token = JWT::encode($payload, 'test-secret', 'HS256');
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $request;
    }

    public function testCreateProduct(): void
    {
        $request = $this->createRequest('POST', '/products', [
            'name' => 'Test Product',
            'cost' => 29.99
        ], true); // authenticated

        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('Test Product', $data['name']);
        $this->assertEquals(29.99, $data['cost']);
    }

    public function testGetAllProducts(): void
    {
        // Create a test product first
        $this->db->exec("INSERT INTO products (name, cost) VALUES ('Existing Product', 19.99)");

        $request = $this->createRequest('GET', '/products');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
    }

    public function testGetProductById(): void
    {
        // Create a test product
        $this->db->exec("INSERT INTO products (name, cost) VALUES ('Specific Product', 39.99)");
        $productId = $this->db->lastInsertId();

        $request = $this->createRequest('GET', "/products/{$productId}");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('Specific Product', $data['name']);
    }

    public function testCreateProductValidationError(): void
    {
        $request = $this->createRequest('POST', '/products', [
            'name' => '', // Invalid: empty name
            'cost' => -10 // Invalid: negative cost
        ], true); // authenticated

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('errors', $data);
    }
}