<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Metrics;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class MetricsMiddleware implements MiddlewareInterface
{
    private Metrics $metrics;

    public function __construct(Metrics $metrics)
    {
        $this->metrics = $metrics;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = microtime(true) - $start;

        // Send metrics to monitoring system
        $this->metrics->recordRequest(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $duration
        );

        return $response;
    }
}