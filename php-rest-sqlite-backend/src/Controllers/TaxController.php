<?php
namespace App\Controllers;

use App\Models\TaxRate;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaxController
{
    public function getAll(Request $request, Response $response): Response
    {
        $rates = TaxRate::fetchAll('SELECT * FROM tax_rates WHERE is_active = 1 ORDER BY name');
        $response->getBody()->write(json_encode($rates));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getDefault(Request $request, Response $response): Response
    {
        $rate = TaxRate::fetchOne('SELECT * FROM tax_rates WHERE is_default = 1 AND is_active = 1 LIMIT 1');
        
        if (!$rate) {
            $response->getBody()->write(json_encode(['error' => 'No default tax rate configured']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($rate));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getByRegion(Request $request, Response $response, array $args): Response
    {
        $region = $args['region'];
        $rate = TaxRate::fetchOne('SELECT * FROM tax_rates WHERE region = :region AND is_active = 1 LIMIT 1', [':region' => $region]);
        
        if (!$rate) {
            // Fall back to default
            return $this->getDefault($request, $response);
        }
        
        $response->getBody()->write(json_encode($rate));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        if (empty($body['name']) || !isset($body['rate'])) {
            $response->getBody()->write(json_encode(['error' => 'name and rate are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $rate = new TaxRate([
                'name' => $body['name'],
                'rate' => (float)$body['rate'],
                'region' => $body['region'] ?? null,
                'is_default' => $body['is_default'] ?? 0,
                'is_active' => $body['is_active'] ?? 1,
                'description' => $body['description'] ?? null,
            ]);
            $rate->save();
            
            $response->getBody()->write(json_encode($rate));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}