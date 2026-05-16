<?php

namespace App\Controllers;

use App\Models\TaxRate;
use App\Services\PaginationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaxController extends ApiController
{
    private PaginationService $paginationService;

    public function __construct(PaginationService $paginationService = null)
    {
        $this->paginationService = $paginationService ?? new PaginationService();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = TaxRate::select()->count();

        $rates = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description', 'is_active'])
                       ->orderBy('name')
                       ->limit($pagination['limit'], $pagination['offset'])
                       ->get();

        $result = $this->paginationService->formatPaginatedResponse($rates, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }

    public function getDefault(Request $request, Response $response): Response
    {
        $rate = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description'])
                      ->where('is_default', '=', 1)
                      ->where('is_active', '=', 1)
                      ->first();
        if (!$rate) {
            return $this->error($response, 'No default tax rate configured', 404);
        }

        return $this->success($response, $rate);
    }

    public function getByRegion(Request $request, Response $response, array $args): Response
    {
        $region = $args['region'];
        $rate = TaxRate::select(['id', 'name', 'rate', 'region', 'is_default', 'description'])
                      ->where('region', '=', $region)
                      ->where('is_active', '=', 1)
                      ->first();
        if (!$rate) {
        // Fall back to default
            return $this->getDefault($request, $response);
        }

        return $this->success($response, $rate);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $rate = TaxRate::find($id);
        if (!$rate) {
            return $this->error($response, 'Tax rate not found', 404);
        }
        return $this->success($response, $rate);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $rate = TaxRate::find($id);
        if (!$rate) {
            return $this->error($response, 'Tax rate not found', 404);
        }

        if (isset($body['name'])) $rate->name = $body['name'];
        if (isset($body['rate'])) $rate->rate = (float)$body['rate'];
        if (isset($body['region'])) $rate->region = $body['region'];
        if (isset($body['is_default'])) $rate->is_default = $body['is_default'];
        if (isset($body['is_active'])) $rate->is_active = $body['is_active'];
        if (isset($body['description'])) $rate->description = $body['description'];

        try {
            $rate->save();
            return $this->success($response, $rate);
        } catch (\Exception $e) {
            return $this->internalError($response);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $rate = TaxRate::find($id);
        if (!$rate) {
            return $this->error($response, 'Tax rate not found', 404);
        }

        try {
            $rate->delete();
            return $response->withStatus(204);
        } catch (\Exception $e) {
            return $this->internalError($response);
        }
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
            return $this->error($response, 'name and rate are required', 400);
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
            return $this->success($response, $rate, 201);
        } catch (\Exception $e) {
            return $this->internalError($response);
        }
    }
}
