<?php

namespace App\Controllers;

use App\Models\TaxRate;
use App\Services\PaginationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaxController
{
    private PaginationService $paginationService;

    public function __construct(PaginationService $paginationService = null)
    {
        $this->paginationService = $paginationService ?? new PaginationService();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = TaxRate::select()->where('is_active', '=', 1)->count();

        // Use optimized query with specific columns and conditions
        $rates = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description'])
                       ->where('is_active', '=', 1)
                       ->orderBy('name')
                       ->limit($pagination['limit'], $pagination['offset'])
                       ->get();

        $result = $this->paginationService->formatPaginatedResponse($rates, $total, $pagination['page'], $pagination['limit']);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getDefault(Request $request, Response $response): Response
    {
        // Use optimized query for default tax rate
        $rate = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description'])
                      ->where('is_default', '=', 1)
                      ->where('is_active', '=', 1)
                      ->first();
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
// Use optimized query for region-specific tax rate
        $rate = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description'])
                      ->where('region', '=', $region)
                      ->where('is_active', '=', 1)
                      ->first();
        if (!$rate) {
        // Fall back to default
            return $this->getDefault($request, $response);
        }

        $response->getBody()->write(json_encode($rate));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function calculateTax(float $amount, ?string $region = null): array
    {
        $rate = null;
        if ($region) {
            $rate = TaxRate::fetchOne('SELECT * FROM tax_rates WHERE region = :region AND is_active = 1 LIMIT 1', [':region' => $region]);
        }

        if (!$rate) {
            $rate = TaxRate::fetchOne('SELECT * FROM tax_rates WHERE is_default = 1 AND is_active = 1 LIMIT 1');
        }

        if (!$rate) {
// Default tax rate if none configured
            $rate = (object)['rate' => 0.0, 'name' => 'No Tax'];
        }

        $taxAmount = $amount * ($rate->rate / 100);
        $totalWithTax = $amount + $taxAmount;
        return [
            'tax_rate' => $rate->rate,
            'tax_amount' => round($taxAmount, 2),
            'total_with_tax' => round($totalWithTax, 2),
        ];
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
