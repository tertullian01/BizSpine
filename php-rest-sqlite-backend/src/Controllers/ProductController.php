<?php
namespace App\Controllers;

use App\Models\Product;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController
{
    public function getAll(Request $request, Response $response): Response
    {
        $products = Product::findAll();
        $response->getBody()->write(json_encode($products));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $product = Product::find($id);
        
        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        if (empty($body['name'])) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $product = new Product($body);
        $product->save();

        if (!$product->id) {
            $response->getBody()->write(json_encode(['error' => 'Failed to create product']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $product = Product::find($id);
        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        foreach ($body as $key => $value) {
            $product->{$key} = $value;
        }
        $product->save();

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $product = Product::find($id);
        if ($product) {
            $product->delete();
        }

        return $response->withStatus(204);
    }
}
