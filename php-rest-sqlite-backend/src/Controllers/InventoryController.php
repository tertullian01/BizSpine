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

class InventoryController extends ApiController
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

        return $this->success($response, $result);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return $this->error($response, 'Inventory record not found', 404);
        }

        return $this->success($response, $inventory);
    }

    public function getByProduct(Request $request, Response $response, array $args): Response
    {
        $productId = (int)$args['id'];
        $inventory = Inventory::findByProduct($productId);
        return $this->success($response, $inventory);
    }

    public function getByStore(Request $request, Response $response, array $args): Response
    {
        $storeId = (int)$args['id'];
        $inventory = Inventory::findByStore($storeId);
        return $this->success($response, $inventory);
    }

    public function getLowStock(Request $request, Response $response): Response
    {
        $inventory = Inventory::findLowStock();
        return $this->success($response, $inventory);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['product_id']) || !isset($body['store_id'])) {
            return $this->error($response, 'product_id and store_id are required', 400);
        }

        // Verify product exists
        if (!Product::find((int)$body['product_id'])) {
            return $this->error($response, 'Product not found', 404);
        }

        // Verify store exists
        if (!Store::find((int)$body['store_id'])) {
            return $this->error($response, 'Store not found', 404);
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
        return $this->success($response, $newInventory, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return $this->error($response, 'Inventory record not found', 404);
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
        return $this->success($response, $updatedInventory);
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
            return $this->error($response, $e->getFirstError(), 400);
        }

        $adjustment = (int)$body['adjustment'];
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return $this->error($response, 'Inventory record not found', 404);
        }

        if (($inventory->quantity + $adjustment) < 0) {
            return $this->error($response, 'Adjustment would result in negative quantity', 400);
        }

        $inventory->adjustQuantity($adjustment);
        $updatedInventory = Inventory::find($inventory->id);
        return $this->success($response, $updatedInventory);
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
