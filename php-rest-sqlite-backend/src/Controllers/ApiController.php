<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController
{
    public function getUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = new User();
        $data = $user->find($id);
        
        if (!$data) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getAllUsers(Request $request, Response $response): Response
    {
        $user = new User();
        $data = $user->all();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createUser(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = new User();
        $result = $user->create($data);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();
        $user = new User();
        $result = $user->update($id, $data);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = new User();
        $user->delete($id);
        return $response->withStatus(204);
    }
}