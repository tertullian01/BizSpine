<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Middleware\AuthMiddleware;
use Firebase\JWT\JWT;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddlewareTest extends TestCase
{
    private string $secret = 'test-secret-key';
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testProcessWithValidToken()
    {
        $middleware = new AuthMiddleware($this->secret);
        $now = time();
        $payload = [
            'iss' => 'test.local',
            'iat' => $now,
            'exp' => $now + 900,
            'sub' => '1',
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');
// Create request with Authorization header
        $request = $this->createRequest('GET', '/api/test')
            ->withHeader('Authorization', "Bearer $token");
// Create a mock handler that will be called if auth succeeds
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
            // Verify user_id was added to request
                $userId = $request->getAttribute('user_id');
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['user_id' => $userId]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        };
        $response = $middleware->process($request, $handler);
        $this->assertTrue($handler->called);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals('1', $body['user_id']);
    }

    public function testProcessWithExpiredToken()
    {
        $middleware = new AuthMiddleware($this->secret);
        $now = time();
        $expiredPayload = [
            'iss' => 'test.local',
            'iat' => $now - 2000,
            'exp' => $now - 1000, // expired
            'sub' => '1',
        ];
        $token = JWT::encode($expiredPayload, $this->secret, 'HS256');
        $request = $this->createRequest('GET', '/api/test')
            ->withHeader('Authorization', "Bearer $token");
        $handler = new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                return new \Slim\Psr7\Response();
            }
        };
        $response = $middleware->process($request, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testProcessWithInvalidTokenSignature()
    {
        $middleware = new AuthMiddleware($this->secret);
        $now = time();
        $payload = [
            'iss' => 'test.local',
            'iat' => $now,
            'exp' => $now + 900,
            'sub' => '1',
        ];
        $token = JWT::encode($payload, 'wrong-secret', 'HS256');
        $request = $this->createRequest('GET', '/api/test')
            ->withHeader('Authorization', "Bearer $token");
        $handler = new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                return new \Slim\Psr7\Response();
            }
        };
        $response = $middleware->process($request, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testProcessWithMissingToken()
    {
        $middleware = new AuthMiddleware($this->secret);
        $request = $this->createRequest('GET', '/api/test');
        $handler = new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                return new \Slim\Psr7\Response();
            }
        };
        $response = $middleware->process($request, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('Missing Authorization header', $body['error']);
    }
}
