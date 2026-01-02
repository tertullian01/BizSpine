<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OptionalAuthMiddleware implements MiddlewareInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        $token = null;

        if (!empty($header)) {
            if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                $token = $matches[1];
            }
        }

        if ($token) {
            try {
                // Support both v5 and v6 of firebase/php-jwt
                if (class_exists(Key::class)) {
                    $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
                } else {
                    $decoded = JWT::decode($token, $this->secret, ['HS256']);
                }

                $request = $request->withAttribute('user_id', $decoded->sub);
                if (isset($decoded->role)) {
                    $request = $request->withAttribute('user_role', $decoded->role);
                }
            } catch (\Exception $e) {
                // Token invalid or expired; proceed as guest
            }
        }

        return $handler->handle($request);
    }
}