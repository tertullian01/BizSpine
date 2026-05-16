<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class SecurityHeadersMiddlewareTest extends TestCase
{
    private SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000',
        ];
        $this->middleware = new SecurityHeadersMiddleware($headers);
    }

    public function testMiddlewareCreation(): void
    {
        $this->assertInstanceOf(SecurityHeadersMiddleware::class, $this->middleware);
    }

    public function testHeadersAreAddedToResponse(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Mock the handler
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        // Mock the response to return itself with headers
        $response->expects($this->exactly(4)) // 4 headers
            ->method('withHeader')
            ->willReturnSelf();

        // Execute middleware
        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testCustomHeaders(): void
    {
        $customHeaders = [
            'Custom-Header' => 'custom-value',
            'Another-Header' => 'another-value',
        ];
        $middleware = new SecurityHeadersMiddleware($customHeaders);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $response->expects($this->exactly(2)) // 2 custom headers
            ->method('withHeader')
            ->willReturnSelf();

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }
}