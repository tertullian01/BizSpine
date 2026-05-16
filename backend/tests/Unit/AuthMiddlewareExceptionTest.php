<?php

namespace Tests\Unit;

use App\Middleware\AuthMiddleware;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use App\Exceptions\ValidationException;

class AuthMiddlewareExceptionTest extends TestCase
{
    public function testMiddlewareShouldNotCatchControllerExceptions()
    {
        $secret = 'test_secret';
        $middleware = new AuthMiddleware($secret);

        $token = JWT::encode(['sub' => '123'], $secret, 'HS256');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer ' . $token);
        $request->method('withAttribute')->willReturnSelf();
        $request->method('getMethod')->willReturn('GET');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new ValidationException('Validation failed'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $middleware->process($request, $handler);
    }
}
