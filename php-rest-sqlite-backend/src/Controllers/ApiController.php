<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class ApiController
{
    /**
     * Return a standardized success response
     */
    protected function success(Response $response, $data, int $status = null): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        $finalStatus = $status ?? $response->getStatusCode();
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($finalStatus);
    }

    /**
     * Return a standardized error response
     */
    protected function error(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /** Generic 500 — do not pass exception messages to clients. */
    protected function internalError(Response $response): Response
    {
        return $this->error($response, 'An internal error occurred', 500);
    }
}
