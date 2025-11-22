<?php
namespace App\Controllers;

use App\Models\Store;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoreController
{
    public function getAll(Request $request, Response $response): Response
    {
        $stores = Store::findAll();
        $response->getBody()->write(json_encode($stores));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $store = Store::find($id);
        
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
        
        if (empty($body['name'])) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validate that name is either 'Siedlung' or 'USA'
        if (!in_array($body['name'], ['Siedlung', 'USA'])) {
            $response->getBody()->write(json_encode(['error' => 'Store name must be either "Siedlung" or "USA"']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (Store::findByName($body['name'])) {
            $response->getBody()->write(json_encode(['error' => 'Store with this name already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $store = new Store($body);
        $store->save();

        $response->getBody()->write(json_encode($store));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $store = Store::find($id);
        if (!$store) {
            $response->getBody()->write(json_encode(['error' => 'Store not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validate that name is either 'Siedlung' or 'USA'
        if (isset($body['name']) && !in_array($body['name'], ['Siedlung', 'USA'])) {
            $response->getBody()->write(json_encode(['error' => 'Store name must be either "Siedlung" or "USA"']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Check for name collision
        if (isset($body['name'])) {
            $existingStore = Store::findByName($body['name']);
            if ($existingStore && $existingStore->id !== $id) {
                $response->getBody()->write(json_encode(['error' => 'Store with this name already exists']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
        }

        foreach ($body as $key => $value) {
            $store->{$key} = $value;
        }
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