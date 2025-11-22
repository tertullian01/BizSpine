<?php

namespace App\Middleware;

use App\Exceptions\ValidationException;
use App\Services\Config;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use Throwable;
use PDOException;
use Slim\Exception\HttpException;

class ErrorHandlerMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
        // Log the full error
            error_log($e);
            $statusCode = 500;
            $message = 'An unexpected internal server error occurred.';
            $type = get_class($e);
            if ($e instanceof HttpException) {
                $statusCode = $e->getCode();
                $message = $e->getMessage();
            } elseif ($e instanceof ValidationException) {
                $statusCode = $e->getCode();
                $message = $e->getMessage();
            } elseif ($e instanceof PDOException) {
    // For database exceptions, don't reveal details in production
                if (Config::get('environment.debug', false)) {
                    $message = 'Database error: ' . $e->getMessage();
                } else {
                    $message = 'A database error occurred.';
                }
                $statusCode = 500;
            } elseif (Config::get('environment.debug', false)) {
    // For other exceptions in debug mode, show more detail
                $message = $e->getMessage();
            }

            $errorPayload = [
                'error' => [
                    'message' => $message,
                ]
            ];
        // Include exception type and trace in debug mode for easier debugging
            if (Config::get('environment.debug', false)) {
                $errorPayload['error']['type'] = $type;
                $errorPayload['error']['trace'] = $e->getTrace();
            }

            $response = new SlimResponse();
            $response->getBody()->write(json_encode($errorPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response
                ->withStatus($statusCode)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
