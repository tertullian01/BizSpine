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

    public function getClients(Request $request, Response $response): Response
    {
        // Get all customers with all available profile data
        $clients = User::fetchAll(
            'SELECT * FROM users WHERE role = :role ORDER BY created_at DESC',
            [':role' => 'customer']
        );

        // Remove sensitive data and enrich each client with order statistics
        $db = \App\Models\BaseModel::$db;
        foreach ($clients as &$client) {
            // Remove password hash from response
            unset($client['password_hash']);
            unset($client['reset_token']);
            unset($client['reset_expires_at']);

            // Get order count and total spent
            $stmt = $db->prepare('
                SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
                FROM orders WHERE user_id = :user_id
            ');
            $stmt->execute([':user_id' => $client['id']]);
            $orderStats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $client['order_count'] = (int) $orderStats['order_count'];
            $client['total_spent'] = (float) $orderStats['total_spent'];

            // Get last order date
            $stmt = $db->prepare('
                SELECT order_date FROM orders
                WHERE user_id = :user_id
                ORDER BY order_date DESC
                LIMIT 1
            ');
            $stmt->execute([':user_id' => $client['id']]);
            $lastOrder = $stmt->fetch(\PDO::FETCH_ASSOC);
            $client['last_order_date'] = $lastOrder ? $lastOrder['order_date'] : null;
        }

        return $this->success($response, $clients);
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

        try {
            $user->delete();
            return $response->withStatus(204);
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') {
                return $this->error($response, 'Cannot delete user because they are associated with other records (e.g., orders, reviews).', 409);
            }
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }
}