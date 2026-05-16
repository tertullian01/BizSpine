<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ClientController extends ApiController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
    }

    public function getAll(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    u.*,
    COUNT(o.id) as order_count,
    COALESCE(SUM(o.total), 0) as total_spent,
    MAX(o.order_date) as last_order_date,
    ur.referral_code,
    COALESCE(ur.points_balance, 0) as points_balance
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
LEFT JOIN user_referrals ur ON u.id = ur.user_id
WHERE u.role = 'customer'
GROUP BY u.id
ORDER BY last_order_date DESC
SQL;

        $stmt = $this->db->query($sql);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data types
        foreach ($clients as &$client) {
            $client['id'] = (int)$client['id'];
            $client['order_count'] = (int)$client['order_count'];
            $client['total_spent'] = (float)$client['total_spent'];
            $client['is_email_verified'] = isset($client['is_email_verified']) ? (int)$client['is_email_verified'] : 0;
            $client['points_balance'] = (int)$client['points_balance'];
            unset($client['password_hash']);
            unset($client['reset_token']);
            unset($client['reset_expires_at']);
        }

        return $this->success($response, ['success' => true, 'data' => $clients]);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    u.*,
    COUNT(o.id) as order_count,
    COALESCE(SUM(o.total), 0) as total_spent,
    MAX(o.order_date) as last_order_date,
    ur.referral_code,
    COALESCE(ur.points_balance, 0) as points_balance
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
LEFT JOIN user_referrals ur ON u.id = ur.user_id
WHERE u.id = :id AND u.role = 'customer'
GROUP BY u.id
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            return $this->error($response, 'Customer not found', 404);
        }

        // Format data types
        $client['id'] = (int)$client['id'];
        $client['order_count'] = (int)$client['order_count'];
        $client['total_spent'] = (float)$client['total_spent'];
        $client['is_email_verified'] = isset($client['is_email_verified']) ? (int)$client['is_email_verified'] : 0;
        $client['points_balance'] = (int)$client['points_balance'];
        unset($client['password_hash']);
        unset($client['reset_token']);
        unset($client['reset_expires_at']);

        return $this->success($response, ['success' => true, 'data' => $client]);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['display_name'])) {
            return $this->error($response, 'Display name is required', 400);
        }

        // Check email uniqueness if provided
        if (!empty($body['email'])) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute([':email' => $body['email']]);
            if ($stmt->fetch()) {
                return $this->error($response, 'Email already exists', 409);
            }
        }

        try {
            $user = new User([
                'email' => !empty($body['email']) ? $body['email'] : null,
                'display_name' => $body['display_name'],
                'password_hash' => null,
                'role' => 'customer',
                'is_email_verified' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'first_name' => $body['first_name'] ?? null,
                'last_name' => $body['last_name'] ?? null,
                'mobile_number' => $body['mobile_number'] ?? null,
                'whatsapp_number' => $body['whatsapp_number'] ?? null,
                'street_line_1' => $body['street_line_1'] ?? null,
                'street_line_2' => $body['street_line_2'] ?? null,
                'city' => $body['city'] ?? null,
                'state' => $body['state'] ?? null,
                'postal_code' => $body['postal_code'] ?? null,
                'country' => $body['country'] ?? null,
            ]);
            $user->save();
            
            return $this->getById($request, $response->withStatus(201), ['id' => $user->id]);
        } catch (\Exception $e) {
            return $this->error($response, 'Error creating client: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = User::find($id);

        if (!$user || $user->role !== 'customer') {
            return $this->error($response, 'Client not found', 404);
        }

        $body = $request->getParsedBody();

        if (array_key_exists('email', $body)) {
            if (!empty($body['email'])) {
                // Check uniqueness
                $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
                $stmt->execute([':email' => $body['email'], ':id' => $id]);
                if ($stmt->fetch()) {
                    return $this->error($response, 'Email already exists', 409);
                }
                $user->email = $body['email'];
            } else {
                $user->email = null;
            }
        }

        if (isset($body['display_name'])) $user->display_name = $body['display_name'];
        if (isset($body['first_name'])) $user->first_name = $body['first_name'];
        if (isset($body['last_name'])) $user->last_name = $body['last_name'];
        if (isset($body['mobile_number'])) $user->mobile_number = $body['mobile_number'];
        if (isset($body['whatsapp_number'])) $user->whatsapp_number = $body['whatsapp_number'];
        if (isset($body['street_line_1'])) $user->street_line_1 = $body['street_line_1'];
        if (isset($body['city'])) $user->city = $body['city'];
        if (isset($body['country'])) $user->country = $body['country'];

        $user->save();

        return $this->getById($request, $response, ['id' => $id]);
    }
}