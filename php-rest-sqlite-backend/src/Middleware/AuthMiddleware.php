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
            
            // Add user_id to request attributes so controllers can access it
            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('token', $decoded);
            
            return $handler->handle($request);
        } catch (\Exception $e) {
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