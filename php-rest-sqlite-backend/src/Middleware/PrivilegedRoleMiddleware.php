<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * JWT authentication plus role allow-list (runs as a single layer to avoid stacking order issues).
 */
class PrivilegedRoleMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $allowedRoles;
    private string $secret;

    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(string $secret, array $allowedRoles)
    {
        $this->secret = $secret;
        $this->allowedRoles = array_values(array_unique(array_map('strval', $allowedRoles)));
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Missing Authorization header');
        }

        if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid Authorization header format');
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $role = $decoded->role ?? 'customer';
            if (!in_array((string) $role, $this->allowedRoles, true)) {
                return $this->forbiddenResponse();
            }

            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('token', $decoded);
        } catch (\Throwable $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }

    private function forbiddenResponse(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
