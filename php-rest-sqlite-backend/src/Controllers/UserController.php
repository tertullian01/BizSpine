<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends ApiController
{
    public function getUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = new User();
        $data = $user->find($id);

        if (!$data) {
            return $this->error($response, 'User not found', 404);
        }

        return $this->success($response, $data);
    }

    public function getAllUsers(Request $request, Response $response): Response
    {
        $user = new User();
        $data = $user->all();
        return $this->success($response, $data);
    }

    public function createUser(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = new User();
        $result = $user->create($data);
        return $this->success($response, $result, 201);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();
        $user = new User();
        $result = $user->update($id, $data);
        return $this->success($response, $result);
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = new User();
        $user->delete($id);
        return $response->withStatus(204);
    }
}