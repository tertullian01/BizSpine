<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Store;
use App\Services\FileUploadService;
use App\Services\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class StoreController extends ApiController
{
    private Validator $validator;
    private FileUploadService $fileUploadService;

    public function __construct(?FileUploadService $fileUploadService = null)
    {
        $this->validator = new Validator();
        $this->fileUploadService = $fileUploadService ?? new FileUploadService(new \App\Services\Logger());
    }

    public function getAll(Request $request, Response $response): Response
    {
        $stores = Store::select(['id', 'name', 'description', 'currency_symbol', 'created_at', 'updated_at'])
                      ->orderBy('name')
                      ->get();
        return $this->success($response, $stores);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $store = Store::select(['id', 'name', 'description', 'currency_symbol', 'created_at', 'updated_at'])
                      ->where('id', '=', $id)
                      ->first();
        if (!$store) {
            return $this->error($response, 'Store not found', 404);
        }

        return $this->success($response, $store);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name'])) || strlen(trim($body['name'])) < 3) {
            return $this->error($response, 'Name is required and must be at least 3 characters', 400);
        }

        $name = trim($body['name']);
        if (Store::findByName($name)) {
            return $this->error($response, 'Store with this name already exists', 409);
        }

        $store = new Store();
        $store->name = $name;
        $store->description = $body['description'] ?? null;
        $store->location = $body['location'] ?? null;
        $store->address = $body['address'] ?? null;
        $store->phone = $body['phone'] ?? null;
        $store->email = $body['email'] ?? null;
        $store->currency_symbol = $body['currency_symbol'] ?? '$';
        $store->save();
        return $this->success($response, $store, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name'])) || strlen(trim($body['name'])) < 3) {
            return $this->error($response, 'Name is required and must be at least 3 characters', 400);
        }

        $name = trim($body['name']);
        $store = Store::find($id);
        if (!$store) {
            return $this->error($response, 'Store not found', 404);
        }

        // Check for name collision
        $existingStore = Store::findByName($name);
        if ($existingStore && $existingStore->id !== $id) {
            return $this->error($response, 'Store with this name already exists', 409);
        }

        $store->name = $name;
        $store->description = $body['description'] ?? $store->description;
        $store->location = $body['location'] ?? $store->location;
        $store->address = $body['address'] ?? $store->address;
        $store->phone = $body['phone'] ?? $store->phone;
        $store->email = $body['email'] ?? $store->email;
        $store->currency_symbol = $body['currency_symbol'] ?? $store->currency_symbol;
        $store->save();
        return $this->success($response, $store);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $store = Store::find($id);
        if (!$store) {
            return $this->error($response, 'Store not found', 404);
        }

        // Check for related records that would prevent deletion
        $db = \App\Models\BaseModel::$db;

        // Check inventory records
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM inventory WHERE store_id = :store_id');
        $stmt->execute([':store_id' => $id]);
        $inventoryCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        // Check order items
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM order_items WHERE store_id = :store_id');
        $stmt->execute([':store_id' => $id]);
        $orderItemsCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        if ($inventoryCount > 0 || $orderItemsCount > 0) {
            $reasons = [];
            if ($inventoryCount > 0) {
                $reasons[] = "{$inventoryCount} inventory record(s)";
            }
            if ($orderItemsCount > 0) {
                $reasons[] = "{$orderItemsCount} order item(s)";
            }
            $reasonText = implode(' and ', $reasons);
            return $this->error($response, "Cannot delete store because it is associated with {$reasonText}. Please reassign or remove these records first.", 409);
        }

        try {
            $store->delete();
            return $response->withStatus(204);
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') {
                return $this->error($response, 'Cannot delete store because it is referenced by other records. Please contact support.', 409);
            }
            return $this->internalError($response);
        }
    }

    /**
     * Upload store logo
     */
    public function uploadLogo(Request $request, Response $response, array $args): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['logo'])) {
            return $this->error($response, 'No logo uploaded', 400);
        }

        $logo = $uploadedFiles['logo'];

        try {
            $result = $this->fileUploadService->uploadFile($logo, 'logo', [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif'],
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
                'max_file_size' => 1 * 1024 * 1024, // 1MB for logos
                'create_thumbnails' => true,
                'thumbnail_size' => [150, 150]
            ]);

            // Optionally save logo URL to store
            $storeId = (int)$args['id'];
            $store = Store::find($storeId);
            if ($store) {
                // Assuming you add a logo_url column to stores table
                // $store->logo_url = $result['url'];
                // $store->save();
            }

            return $this->success($response, [
                'message' => 'Logo uploaded successfully',
                'file' => $result
            ]);

        } catch (\Exception $e) {
            return $this->error($response, 'Upload failed: ' . $e->getMessage(), 400);
        }
    }
}
