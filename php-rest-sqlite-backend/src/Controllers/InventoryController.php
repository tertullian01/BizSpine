<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Services\Database;
use App\Services\PaginationService;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class InventoryController
{
    private PDO $db;
    private Validator $validator;
    private PaginationService $paginationService;

    public function __construct(PDO $db = null, PaginationService $paginationService = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->validator = new Validator();
        $this->paginationService = $paginationService ?? new PaginationService();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $page = $pagination['page'];
        $limit = $pagination['limit'];
        $offset = $pagination['offset'];

        // Get total count
        $countStmt = $this->db->query('SELECT COUNT(*) as total FROM inventory');
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = <<<'SQL'
SELECT
    i.*,
    p.name as product_name,
    s.name as store_name
FROM inventory i
LEFT JOIN products p ON i.product_id = p.id
LEFT JOIN stores s ON i.store_id = s.id
ORDER BY s.name, p.name
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $inventory = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Inventory');

        $result = $this->paginationService->formatPaginatedResponse($inventory, $total, $page, $limit);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $inventory = Inventory::find($id);
        if (!$inventory) {
            $response->getBody()->write(json_encode(['error' => 'Inventory record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($inventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getByProduct(Request $request, Response $response, array $args): Response
    {
        $productId = (int)$args['id'];
        $inventory = Inventory::findByProduct($productId);
        $response->getBody()->write(json_encode($inventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getByStore(Request $request, Response $response, array $args): Response
    {
        $storeId = (int)$args['id'];
        $inventory = Inventory::findByStore($storeId);
        $response->getBody()->write(json_encode($inventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getLowStock(Request $request, Response $response): Response
    {
        $inventory = Inventory::findLowStock();
        $response->getBody()->write(json_encode($inventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['product_id']) || !isset($body['store_id'])) {
            $response->getBody()->write(json_encode(['error' => 'product_id and store_id are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verify product exists
        if (!Product::find((int)$body['product_id'])) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verify store exists
        if (!Store::find((int)$body['store_id'])) {
            $response->getBody()->write(json_encode(['error' => 'Store not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $inventory = new Inventory();
        $inventory->product_id = (int)$body['product_id'];
        $inventory->store_id = (int)$body['store_id'];
        $inventory->quantity = isset($body['quantity']) ? (int)$body['quantity'] : 0;
        $inventory->min_quantity = isset($body['min_quantity']) ? (int)$body['min_quantity'] : 0;
        $inventory->max_quantity = isset($body['max_quantity']) ? (int)$body['max_quantity'] : null;
        $inventory->save();
// Fetch the full record with names for the response
        $newInventory = Inventory::find($inventory->id);
        $response->getBody()->write(json_encode($newInventory));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $inventory = Inventory::find($id);
        if (!$inventory) {
            $response->getBody()->write(json_encode(['error' => 'Inventory record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $updates = [];
        $params = [':id' => $id];
        if ($quantity !== null) {
            $updates[] = 'quantity = :quantity';
            $params[':quantity'] = $quantity;
            $updates[] = 'last_restocked = datetime("now")';
        }
        if ($minQuantity !== null) {
            $updates[] = 'min_quantity = :min_quantity';
            $params[':min_quantity'] = $minQuantity;
        }
        if ($maxQuantity !== null) {
            $updates[] = 'max_quantity = :max_quantity';
            $params[':max_quantity'] = $maxQuantity;
        }

        if (empty($updates)) {
            throw new ValidationException('No valid fields to update');
        }

        $inventory->quantity = $body['quantity'] ?? $inventory->quantity;
        $inventory->min_quantity = $body['min_quantity'] ?? $inventory->min_quantity;
        $inventory->max_quantity = $body['max_quantity'] ?? $inventory->max_quantity;
        $inventory->save();
// Fetch the full record with names for the response
        $updatedInventory = Inventory::find($inventory->id);
        $response->getBody()->write(json_encode($updatedInventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function adjustQuantity(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        try {
            $this->validator->validate($body, [
                'adjustment' => v::notOptional(v::intVal())->setName('Adjustment'),
            ]);
        } catch (ValidationException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getFirstError()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $adjustment = (int)$body['adjustment'];
        $inventory = Inventory::find($id);
        if (!$inventory) {
            $response->getBody()->write(json_encode(['error' => 'Inventory record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if (($inventory->quantity + $adjustment) < 0) {
            $response->getBody()->write(json_encode(['error' => 'Adjustment would result in negative quantity']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $inventory->adjustQuantity($adjustment);
        $updatedInventory = Inventory::find($inventory->id);
        $response->getBody()->write(json_encode($updatedInventory));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $inventory = Inventory::find($id);
        if ($inventory) {
            $inventory->delete();
        }

        return $response->withStatus(204);
    }
}
