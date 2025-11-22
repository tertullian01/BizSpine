<?php

namespace App\Controllers;

use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use App\Models\Expense;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReturnController
{
    public function getAll(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    r.*,
    o.order_number,
    u.email as user_email
FROM returns r
LEFT JOIN orders o ON r.order_id = o.id
LEFT JOIN users u ON r.user_id = u.id
ORDER BY r.created_at DESC
SQL;
        $returns = OrderReturn::fetchAll($sql);
        foreach ($returns as $return) {
            $return->items = $return->getItems();
        }

        $response->getBody()->write(json_encode($returns));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    r.*,
    o.order_number,
    u.email as user_email
FROM returns r
LEFT JOIN orders o ON r.order_id = o.id
LEFT JOIN users u ON r.user_id = u.id
WHERE r.id = :id
SQL;
        $return = OrderReturn::fetchOne($sql, [':id' => $id]);
        if (!$return) {
            $response->getBody()->write(json_encode(['error' => 'Return not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $return->items = $return->getItems();
        $response->getBody()->write(json_encode($return));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $return = OrderReturn::createReturn($body, $userId);
            return $this->getById($request, $response->withStatus(201), ['id' => $return->id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        try {
            $return = OrderReturn::find($id);
            if (!$return) {
                throw new \Exception('Return not found');
            }

            $return->approve();
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function processRefund(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        try {
            $return = OrderReturn::find($id);
            if (!$return) {
                throw new \Exception('Return not found');
            }

            $return->processRefund($body);
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
