<?php

namespace App\Controllers;

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
    MAX(o.order_date) as last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
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
    MAX(o.order_date) as last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
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
        unset($client['password_hash']);
        unset($client['reset_token']);
        unset($client['reset_expires_at']);

        return $this->success($response, ['success' => true, 'data' => $client]);
    }
}