<?php

namespace Tests\Unit;

use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class CorsMiddlewareTest extends TestCase
{
    public function testAllowsConfiguredOriginOnPreflight(): void
    {
        $middleware = new CorsMiddleware(['https://example.com', 'https://www.example.com']);
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', 'https://api.example.com/auth/login')
            ->withHeader('Origin', 'https://www.example.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $response = $middleware->process($request, $this->unexpectedHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://www.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testRejectsUnknownOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://example.com']);
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', 'https://api.example.com/auth/login')
            ->withHeader('Origin', 'https://evil.example');

        $response = $middleware->process($request, $this->unexpectedHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAllowsLocalhostInDevOriginList(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'http://localhost:8000/auth/login')
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    private function unexpectedHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                self::fail('Preflight should not reach the route handler');
            }
        };
    }
}
