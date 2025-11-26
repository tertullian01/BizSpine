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
        // Use optimized query with specific columns
        $stores = Store::select(['id', 'name', 'description', 'created_at', 'updated_at'])
                      ->orderBy('name')
                      ->get();
        return $this->success($response, $stores);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
// Use optimized query for single store
        $store = Store::findWithColumns($id);
        if (!$store) {
            return $this->error($response, 'Store not found', 404);
        }

        return $this->success($response, $store);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name']))) {
            return $this->error($response, 'Name is required', 400);
        }

        $name = trim($body['name']);
        if (Store::findByName($name)) {
            return $this->error($response, 'Store with this name already exists', 409);
        }

        $store = new Store();
        $store->name = $name;
        $store->description = $body['description'] ?? null;
        $store->address = $body['address'] ?? null;
        $store->phone = $body['phone'] ?? null;
        $store->email = $body['email'] ?? null;
        $store->save();
        return $this->success($response, $store, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        if (!isset($body['name']) || empty(trim($body['name']))) {
            return $this->error($response, 'Name is required', 400);
        }

        $name = trim($body['name']);
 // Check if store exists
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
        $store->address = $body['address'] ?? $store->address;
        $store->phone = $body['phone'] ?? $store->phone;
        $store->email = $body['email'] ?? $store->email;
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

        $store->delete();
        return $response->withStatus(204);
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
