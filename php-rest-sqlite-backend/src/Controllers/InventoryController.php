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

        $queryParams = $request->getQueryParams();
        $search = isset($queryParams['search']) ? trim($queryParams['search']) : null;
        $sort = $queryParams['sort'] ?? null;
        $order = strtoupper($queryParams['order'] ?? 'ASC');

        $whereClause = "";
        $params = [];

        if ($search) {
            $whereClause = "WHERE p.name LIKE :search OR s.name LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        // Determine sort order
        $allowedSorts = [
            'product_name' => 'p.name',
            'store_name' => 's.name',
            'quantity' => 'i.quantity',
            'last_restocked' => 'i.last_restocked',
            'min_quantity' => 'i.min_quantity',
            'max_quantity' => 'i.max_quantity'
        ];

        if ($sort && isset($allowedSorts[$sort])) {
            $sortColumn = $allowedSorts[$sort];
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'ASC';
            }
            $orderByClause = "ORDER BY $sortColumn $order";
        } else {
            $orderByClause = "ORDER BY s.name, p.name";
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM inventory i LEFT JOIN products p ON i.product_id = p.id LEFT JOIN stores s ON i.store_id = s.id $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = <<<SQL
SELECT
    i.*,
    p.name as product_name,
    p.cost as product_cost,
    s.name as store_name
FROM inventory i
LEFT JOIN products p ON i.product_id = p.id
LEFT JOIN stores s ON i.store_id = s.id
$whereClause
$orderByClause
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
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

        // Check if inventory already exists
        $existing = Inventory::fetchOne('SELECT id FROM inventory WHERE product_id = :pid AND store_id = :sid', [
            ':pid' => (int)$body['product_id'],
            ':sid' => (int)$body['store_id']
        ]);
        if ($existing) {
            return $this->error($response, 'Inventory record already exists for this product and store', 409);
        }

        $inventory = new Inventory();
        $inventory->product_id = (int)$body['product_id'];
        $inventory->store_id = (int)$body['store_id'];
        $inventory->quantity = isset($body['quantity']) ? (int)$body['quantity'] : 0;
        $inventory->min_quantity = isset($body['min_quantity']) ? (int)$body['min_quantity'] : 0;
        $inventory->max_quantity = isset($body['max_quantity']) ? (int)$body['max_quantity'] : null;
        $inventory->price_override = isset($body['price_override']) ? (float)$body['price_override'] : null;
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

        // Handle alias for min_quantity
        if (isset($body['low_stock_threshold']) && !isset($body['min_quantity'])) {
            $body['min_quantity'] = $body['low_stock_threshold'];
        }

        $quantity = isset($body['quantity']) ? (int)$body['quantity'] : null;
        $minQuantity = isset($body['min_quantity']) ? (int)$body['min_quantity'] : null;
        $maxQuantity = isset($body['max_quantity']) ? (int)$body['max_quantity'] : null;
        $hasPriceOverride = array_key_exists('price_override', $body);
        $priceOverride = $hasPriceOverride && $body['price_override'] !== null ? (float)$body['price_override'] : null;
        $storeId = isset($body['store_id']) ? (int)$body['store_id'] : null;
        $productId = isset($body['product_id']) ? (int)$body['product_id'] : null;

        $hasUpdates = false;

        // Handle store or product changes
        if (($storeId !== null && $storeId !== $inventory->store_id) || 
            ($productId !== null && $productId !== $inventory->product_id)) {
            
            $newStoreId = $storeId ?? $inventory->store_id;
            $newProductId = $productId ?? $inventory->product_id;

            // Verify existence
            if ($storeId !== null && !Store::find($newStoreId)) {
                return $this->error($response, 'Store not found', 404);
            }
            if ($productId !== null && !Product::find($newProductId)) {
                return $this->error($response, 'Product not found', 404);
            }

            // Check uniqueness
            $existing = Inventory::fetchOne('SELECT id FROM inventory WHERE product_id = :pid AND store_id = :sid AND id != :id', [
                ':pid' => $newProductId,
                ':sid' => $newStoreId,
                ':id' => $id
            ]);
            if ($existing) {
                return $this->error($response, 'Inventory record already exists for this product and store', 409);
            }

            if ($storeId !== null) $inventory->store_id = $storeId;
            if ($productId !== null) $inventory->product_id = $productId;
            
            $hasUpdates = true;
        }

        if ($quantity !== null) {
            $inventory->quantity = $quantity;
            $inventory->last_restocked = date('Y-m-d H:i:s');
            $hasUpdates = true;
        }
        if ($minQuantity !== null) {
            $inventory->min_quantity = $minQuantity;
            $hasUpdates = true;
        }
        if ($maxQuantity !== null) {
            $inventory->max_quantity = $maxQuantity;
            $hasUpdates = true;
        }
        if ($hasPriceOverride) {
            $inventory->price_override = $priceOverride;
            $hasUpdates = true;
        }

        if (!$hasUpdates) {
            throw new ValidationException('No valid fields to update');
        }

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
