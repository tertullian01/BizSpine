<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;

    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $allowCredentials = true
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Get the origin from the request
        $origin = $request->getHeaderLine('Origin');

        // Check if the origin is allowed
        $allowedOrigin = $this->isOriginAllowed($origin);

        // Handle preflight OPTIONS requests directly
        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse();
            $response = $response->withStatus(200);

            if ($allowedOrigin) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
            }

            $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            $response = $response->withHeader('Access-Control-Max-Age', '86400'); // 24 hours

            if ($this->allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }

            return $response;
        }

        // For non-OPTIONS requests, process normally and add CORS headers
        $response = $handler->handle($request);

        if ($allowedOrigin) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): ?string
    {
        // If wildcard is allowed, return the requesting origin
        if (in_array('*', $this->allowedOrigins)) {
            return $origin ?: '*';
        }

        // Check if the origin is in the allowed list
        if (in_array($origin, $this->allowedOrigins)) {
            return $origin;
        }

        return null;
    }
}