<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\MetricsMiddleware;
use App\Services\Logger;
use App\Services\Metrics;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class MetricsMiddlewareTest extends TestCase
{
    private MetricsMiddleware $middleware;
    private MockObject $metrics;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->metrics = $this->createMock(Metrics::class);

        $this->middleware = new MetricsMiddleware($this->metrics);
    }

    public function testMiddlewareCreation(): void
    {
        $this->assertInstanceOf(MetricsMiddleware::class, $this->middleware);
    }

    public function testProcessRecordsMetrics(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $uri = $this->createMock(UriInterface::class);

        // Setup request mocks
        $request->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $request->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        $uri->expects($this->once())
            ->method('getPath')
            ->willReturn('/api/test');

        // Setup response mock
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        // Setup handler mock
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        // Expect metrics to be recorded
        $this->metrics->expects($this->once())
            ->method('recordRequest')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo('/api/test'),
                $this->equalTo(200),
                $this->isType('float')
            );

        // Execute middleware
        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessHandlesDifferentMethodsAndStatuses(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $request->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        $uri->expects($this->once())
            ->method('getPath')
            ->willReturn('/api/create');

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(201);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $this->metrics->expects($this->once())
            ->method('recordRequest')
            ->with('POST', '/api/create', 201, $this->isType('float'));

        $result = $this->middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }
}