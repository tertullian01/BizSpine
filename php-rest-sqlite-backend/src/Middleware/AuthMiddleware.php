<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        error_log("AUTH_MIDDLEWARE: Processing request to: " . $request->getUri()->getPath() . " method: " . $request->getMethod());

        // Skip authentication for OPTIONS requests (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            error_log("AUTH_MIDDLEWARE: Allowing OPTIONS request through without authentication");
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');
        error_log("AUTH_MIDDLEWARE: Authorization header: " . ($authHeader ? substr($authHeader, 0, 20) . "..." : "missing"));

        if (empty($authHeader)) {
            error_log("AUTH_MIDDLEWARE: No Authorization header found");
            return $this->unauthorizedResponse('Missing Authorization header');
        }

        if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            error_log("AUTH_MIDDLEWARE: Authorization header format invalid");
            return $this->unauthorizedResponse('Invalid Authorization header format');
        }

        $token = $matches[1];
        error_log("AUTH_MIDDLEWARE: Token extracted, length: " . strlen($token));

        try {
            error_log("AUTH_MIDDLEWARE: Attempting to decode JWT token");
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            error_log("AUTH_MIDDLEWARE: JWT decoded successfully, user_id: " . ($decoded->sub ?? 'unknown'));

            // Add user_id to request attributes so controllers can access it
            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('token', $decoded);

            error_log("AUTH_MIDDLEWARE: Authentication successful, proceeding to controller");
            return $handler->handle($request);
        } catch (\Exception $e) {
            error_log("AUTH_MIDDLEWARE: JWT decode failed: " . $e->getMessage());
            return $this->unauthorizedResponse('Invalid or expired token: ' . $e->getMessage());
        }
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
