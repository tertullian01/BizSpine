<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends ApiController
{
    public function getUser(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        return $this->success($response, $user);
    }

    public function getAllUsers(Request $request, Response $response): Response
    {
        $users = User::fetchAll('SELECT * FROM users ORDER BY created_at DESC');
        return $this->success($response, $users);
    }

    public function getCustomers(Request $request, Response $response): Response
    {
        $customers = User::fetchAll('SELECT * FROM users WHERE role = :role ORDER BY created_at DESC', [':role' => 'customer']);
        return $this->success($response, $customers);
    }

    public function createUser(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = new User($data);
        $user->save();
        return $this->success($response, $user, 201);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $data = $request->getParsedBody();
        foreach ($data as $key => $value) {
            if (property_exists($user, $key)) {
                $user->$key = $value;
            }
        }
        $user->save();

        return $this->success($response, $user);
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $user->delete();
        return $response->withStatus(204);
    }
}