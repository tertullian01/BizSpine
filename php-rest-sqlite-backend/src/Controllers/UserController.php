<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Order;
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

    public function getUserOrders(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $sql = <<<'SQL'
SELECT 
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE o.user_id = :user_id
ORDER BY o.order_date DESC
SQL;
        $orders = Order::fetchAll($sql, [':user_id' => $id]);
        foreach ($orders as $order) {
            $order->items = $order->getItems();
        }

        return $this->success($response, $orders);
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
        $clientsArray = [];
        foreach ($clients as $client) {
            // Convert User object to array
            $clientData = (array) $client;

            // Remove password hash from response
            unset($clientData['password_hash']);
            unset($clientData['reset_token']);
            unset($clientData['reset_expires_at']);

            // Get order count and total spent
            $stmt = $db->prepare('
                SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
                FROM orders WHERE user_id = :user_id
            ');
            $stmt->execute([':user_id' => $clientData['id']]);
            $orderStats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $clientData['order_count'] = (int) $orderStats['order_count'];
            $clientData['total_spent'] = (float) $orderStats['total_spent'];

            // Get last order date
            $stmt = $db->prepare('
                SELECT order_date FROM orders
                WHERE user_id = :user_id
                ORDER BY order_date DESC
                LIMIT 1
            ');
            $stmt->execute([':user_id' => $clientData['id']]);
            $lastOrder = $stmt->fetch(\PDO::FETCH_ASSOC);
            $clientData['last_order_date'] = $lastOrder ? $lastOrder['order_date'] : null;

            // Get referral info
            $stmt = $db->prepare('
                SELECT referral_code, points_balance 
                FROM user_referrals 
                WHERE user_id = :user_id
            ');
            $stmt->execute([':user_id' => $clientData['id']]);
            $referralInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
            $clientData['referral_code'] = $referralInfo ? $referralInfo['referral_code'] : null;
            $clientData['points_balance'] = $referralInfo ? (int) $referralInfo['points_balance'] : 0;

            $clientsArray[] = $clientData;
        }
        $clients = $clientsArray;

        return $this->success($response, $clients);
    }

    public function getClient(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user || $user->role !== 'customer') {
            return $this->error($response, 'Client not found', 404);
        }

        // Convert to array and add order statistics
        $clientData = (array) $user;
        unset($clientData['password_hash']);
        unset($clientData['reset_token']);
        unset($clientData['reset_expires_at']);

        // Get order count and total spent
        $db = \App\Models\BaseModel::$db;
        $stmt = $db->prepare('
            SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
            FROM orders WHERE user_id = :user_id
        ');
        $stmt->execute([':user_id' => $id]);
        $orderStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $clientData['order_count'] = (int) $orderStats['order_count'];
        $clientData['total_spent'] = (float) $orderStats['total_spent'];

        // Get last order date
        $stmt = $db->prepare('
            SELECT order_date FROM orders
            WHERE user_id = :user_id
            ORDER BY order_date DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $id]);
        $lastOrder = $stmt->fetch(\PDO::FETCH_ASSOC);
        $clientData['last_order_date'] = $lastOrder ? $lastOrder['order_date'] : null;

        // Get referral info
        $stmt = $db->prepare('
            SELECT referral_code, points_balance 
            FROM user_referrals 
            WHERE user_id = :user_id
        ');
        $stmt->execute([':user_id' => $id]);
        $referralInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        $clientData['referral_code'] = $referralInfo ? $referralInfo['referral_code'] : null;
        $clientData['points_balance'] = $referralInfo ? (int) $referralInfo['points_balance'] : 0;

        return $this->success($response, $clientData);
    }

    public function updateClient(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user || $user->role !== 'customer') {
            return $this->error($response, 'Client not found', 404);
        }

        $data = $request->getParsedBody();
        unset($data['id']);
        foreach ($data as $key => $value) {
            if (property_exists($user, $key)) {
                $user->$key = $value;
            }
        }
        $user->save();

        // Return updated client data with order statistics
        $clientData = (array) $user;
        unset($clientData['password_hash']);
        unset($clientData['reset_token']);
        unset($clientData['reset_expires_at']);

        // Get order count and total spent
        $db = \App\Models\BaseModel::$db;
        $stmt = $db->prepare('
            SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent
            FROM orders WHERE user_id = :user_id
        ');
        $stmt->execute([':user_id' => $id]);
        $orderStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $clientData['order_count'] = (int) $orderStats['order_count'];
        $clientData['total_spent'] = (float) $orderStats['total_spent'];

        // Get last order date
        $stmt = $db->prepare('
            SELECT order_date FROM orders
            WHERE user_id = :user_id
            ORDER BY order_date DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $id]);
        $lastOrder = $stmt->fetch(\PDO::FETCH_ASSOC);
        $clientData['last_order_date'] = $lastOrder ? $lastOrder['order_date'] : null;

        // Get referral info
        $stmt = $db->prepare('
            SELECT referral_code, points_balance 
            FROM user_referrals 
            WHERE user_id = :user_id
        ');
        $stmt->execute([':user_id' => $id]);
        $referralInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        $clientData['referral_code'] = $referralInfo ? $referralInfo['referral_code'] : null;
        $clientData['points_balance'] = $referralInfo ? (int) $referralInfo['points_balance'] : 0;

        return $this->success($response, $clientData);
    }

    public function createUser(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Handle password separately
        $password = $data['password'] ?? null;
        unset($data['password']);
        unset($data['id']);

        $user = new User($data);

        // Hash password if provided
        if ($password) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        }

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
        unset($data['id']);
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

    public function updateUserPassword(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['password']) || strlen($data['password']) < 8) {
            return $this->error($response, 'Password must be at least 8 characters long', 400);
        }

        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $user->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->save();

        return $this->success($response, ['message' => 'Password updated successfully']);
    }

    public function updateClientPassword(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['password']) || strlen($data['password']) < 8) {
            return $this->error($response, 'Password must be at least 8 characters long', 400);
        }

        $user = User::find($id);

        if (!$user || $user->role !== 'customer') {
            return $this->error($response, 'Client not found', 404);
        }

        $user->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->save();

        return $this->success($response, ['message' => 'Client password updated successfully']);
    }
}