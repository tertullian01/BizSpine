<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function index(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'time' => date(DATE_ATOM),
            'app' => $this->config['environment']['app_name'] ?? 'PHP REST API'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}