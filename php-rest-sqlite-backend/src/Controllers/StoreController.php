<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Store;
use App\Services\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class StoreController
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function getAll(Request $request, Response $response): Response
    {
        // Use optimized query with specific columns
        $stores = Store::select(['id', 'name', 'description', 'created_at', 'updated_at'])
                      ->orderBy('name')
                      ->get();
        $response->getBody()->write(json_encode($stores));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
// Use optimized query for single store
        $store = Store::findWithColumns($id);
        if (!$store) {
            $response->getBody()->write(json_encode(['error' => 'Store not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($store));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name']))) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!in_array($body['name'], ['Siedlung', 'USA'])) {
            $response->getBody()->write(json_encode(['error' => 'Store name must be either "Siedlung" or "USA"']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $name = $body['name'];
        if (Store::findByName($name)) {
            $response->getBody()->write(json_encode(['error' => 'Store with this name already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $store = new Store();
        $store->name = $name;
        $store->description = $body['description'] ?? null;
        $store->address = $body['address'] ?? null;
        $store->phone = $body['phone'] ?? null;
        $store->email = $body['email'] ?? null;
        $store->save();
        $response->getBody()->write(json_encode($store));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name']))) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!in_array($body['name'], ['Siedlung', 'USA'])) {
            $response->getBody()->write(json_encode(['error' => 'Store name must be either "Siedlung" or "USA"']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $name = $body['name'];
// Check if store exists
        $store = Store::find($id);
        if (!$store) {
            $response->getBody()->write(json_encode(['error' => 'Store not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Check for name collision
        $existingStore = Store::findByName($name);
        if ($existingStore && $existingStore->id !== $id) {
            $response->getBody()->write(json_encode(['error' => 'Store with this name already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $store->name = $name;
        $store->description = $body['description'] ?? $store->description;
        $store->address = $body['address'] ?? $store->address;
        $store->phone = $body['phone'] ?? $store->phone;
        $store->email = $body['email'] ?? $store->email;
        $store->save();
        $response->getBody()->write(json_encode($store));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $store = Store::find($id);
        if (!$store) {
            $response->getBody()->write(json_encode(['error' => 'Store not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $store->delete();
        return $response->withStatus(204);
    }
}
