<?php

namespace App\Controllers;

use App\Models\Category;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CategoryController extends ApiController
{
    public function getAll(Request $request, Response $response): Response
    {
        $categories = Category::findAll();
        return $this->success($response, $categories);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $category = Category::find($id);
        if (!$category) {
            return $this->error($response, 'Category not found', 404);
        }
        return $this->success($response, $category);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['name']) || empty($body['type'])) {
            return $this->error($response, 'Name and type are required', 400);
        }

        if (!in_array($body['type'], ['income', 'expense'])) {
            return $this->error($response, 'Type must be income or expense', 400);
        }

        $category = new Category([
            'name' => $body['name'],
            'type' => $body['type'],
            'color' => $body['color'] ?? null,
            'icon' => $body['icon'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $category->save();

        return $this->success($response, $category, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $category = Category::find($id);
        if (!$category) {
            return $this->error($response, 'Category not found', 404);
        }

        $body = $request->getParsedBody();

        if (isset($body['name'])) $category->name = $body['name'];
        if (isset($body['type'])) {
            if (!in_array($body['type'], ['income', 'expense'])) {
                return $this->error($response, 'Type must be income or expense', 400);
            }
            $category->type = $body['type'];
        }
        if (isset($body['color'])) $category->color = $body['color'];
        if (isset($body['icon'])) $category->icon = $body['icon'];

        $category->updated_at = date('Y-m-d H:i:s');
        $category->save();

        return $this->success($response, $category);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $category = Category::find($id);
        if ($category) {
            $category->delete();
        }
        return $response->withStatus(204);
    }
}