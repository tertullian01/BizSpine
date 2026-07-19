<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\BaseModel;
use App\Services\Container;
use App\Services\Database;
use Firebase\JWT\JWT;
use Slim\Factory\AppFactory;
use Tests\DatabaseTestCase;

class SystemExportApiTest extends DatabaseTestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();

        BaseModel::setDatabase(self::$db);
        Database::setInstance(self::$db);

        $container = new Container();
        AppFactory::setContainer($container);

        $container->bind(\App\Middleware\AuthMiddleware::class, fn ($c) => new \App\Middleware\AuthMiddleware('test-secret'));
        $container->bind(\App\Controllers\SystemController::class, fn ($c) => new \App\Controllers\SystemController(self::$db));

        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(true, true, true);

        \App\Routes\SystemRoutes::register($this->app);
    }

    private function authHeader(int $userId, string $role = 'admin'): array
    {
        $token = JWT::encode([
            'iss' => 'test',
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => (string) $userId,
            'role' => $role,
        ], 'test-secret', 'HS256');

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function testExportRequiresAuth(): void
    {
        $request = $this->createRequest('GET', '/system/export');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testExportRequiresAdmin(): void
    {
        self::$db->exec("INSERT INTO users (email, password_hash, role) VALUES ('customer@example.com', 'hash', 'customer')");
        $userId = (int) self::$db->lastInsertId();

        $request = $this->createRequest('GET', '/system/export', $this->authHeader($userId, 'customer'));
        $response = $this->app->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAdminCanDownloadZipExport(): void
    {
        self::$db->exec("INSERT INTO users (email, password_hash, role) VALUES ('admin@example.com', 'hash', 'admin')");
        $userId = (int) self::$db->lastInsertId();
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Zip Product', 12.50)");

        $request = $this->createRequest('GET', '/system/export', $this->authHeader($userId, 'admin'));
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('application/zip', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="database-export-', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame("PK\x03\x04", substr((string) $response->getBody(), 0, 4));
        $this->assertGreaterThan(0, (int) $response->getHeaderLine('X-Export-Table-Count'));
    }
}
