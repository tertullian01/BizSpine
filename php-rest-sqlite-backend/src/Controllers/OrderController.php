<?php
namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OrderController
{
    public function getAll(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
ORDER BY o.order_date DESC
SQL;
        
        $orders = Order::fetchAll($sql);
        
        foreach ($orders as $order) {
            $order->items = $order->getItems();
        }
        
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getMyOrders(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
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
        
        $orders = Order::fetchAll($sql, [':user_id' => $userId]);
        
        foreach ($orders as $order) {
            $order->items = $order->getItems();
        }
        
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    o.*,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE o.id = :id
SQL;
        
        $order = Order::fetchOne($sql, [':id' => $id]);
        
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $order->items = $order->getItems();
        
        $response->getBody()->write(json_encode($order));
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
            $order = Order::createOrder($body, $userId);
            return $this->getById($request, $response->withStatus(201), ['id' => $order->id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $order = Order::find($id);
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        try {
            $order->updateOrder($body);
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function addPayment(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $order = Order::find($id);
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        try {
            $income = $order->addPayment($body);
            
            $response->getBody()->write(json_encode([
                'message' => 'Payment recorded successfully',
                'order_id' => $id,
                'amount' => (float)$body['amount'],
                'income_id' => $income->id,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error recording payment: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $order = Order::find($id);
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        try {
            $order->cancel();
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}