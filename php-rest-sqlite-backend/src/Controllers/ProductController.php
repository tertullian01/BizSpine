<?php

namespace App\Controllers;

use App\Models\Product;
use App\Services\CacheableProductService;
use App\Services\Logger;
use App\Middleware\FileUploadMiddleware;
use App\Services\PaginationService;
use PDO;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

/**
 * @OA\OpenApi(
 *     @OA\Info(title="Product API", version="1.0.0"),
 *     @OA\Server(url="http://localhost:8000")
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Sample Product"),
 *     @OA\Property(property="type", type="string", example="food"),
 *     @OA\Property(property="cost", type="number", format="float", example=29.99),
 *     @OA\Property(property="description", type="string", example="Product description"),
 *     @OA\Property(property="size", type="string", example="500ml"),
 *     @OA\Property(property="image_url", type="string", example="http://example.com/image.jpg"),
 *     @OA\Property(property="state", type="string", enum={"For Sale", "Discontinued"}, example="For Sale"),
 *     @OA\Property(property="featured_ingredients", type="string", example="Aloe Vera, Honey"),
 *     @OA\Property(property="all_ingredients", type="string", example="Water, Aloe Vera, Honey, ..."),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(property="error", type="string", example="Product not found")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="errors", type="object", example={"name": "Name is required", "cost": "Cost must be positive"})
 * )
 *
 * @OA\Schema(
 *     schema="Pagination",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="total", type="integer", example=150),
 *     @OA\Property(property="total_pages", type="integer", example=8),
 *     @OA\Property(property="has_next", type="boolean", example=true),
 *     @OA\Property(property="has_prev", type="boolean", example=false),
 *     @OA\Property(property="next_page", type="integer", nullable=true, example=2),
 *     @OA\Property(property="prev_page", type="integer", nullable=true, example=null)
 * )
 */
class ProductController extends ApiController
{
    private PDO $db;
    private CacheableProductService $cacheableProductService;
    private Logger $logger;
    private PaginationService $paginationService;

    public function __construct(CacheableProductService $cacheableProductService, Logger $logger, PaginationService $paginationService, PDO $db)
    {
        $this->cacheableProductService = $cacheableProductService;
        $this->logger = $logger;
        $this->paginationService = $paginationService;
        $this->db = $db;
    }

    /**
     * @OA\Get(
     *     path="/products",
     *     summary="Get all products with pagination",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of products",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $page = $pagination['page'];
        $limit = $pagination['limit'];
        $offset = $pagination['offset'];

        // Get total count for pagination
        $total = Product::count();

        // Use optimized query with specific columns, excluding large text fields
        $products = Product::select(['id', 'name', 'type', 'description', 'size', 'cost', 'image_url', 'state', 'created_at', 'updated_at'])
                            ->orderBy('name')
                            ->limit($limit, $offset)
                            ->get();

        $result = $this->paginationService->formatPaginatedResponse($products, $total, $page, $limit);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/products/type/{type}",
     *     summary="Get products by type",
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID to fetch inventory data",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of products by type",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getByType(Request $request, Response $response, array $args): Response
    {
        $type = $args['type'];
        $queryParams = $request->getQueryParams();
        $storeId = isset($queryParams['store_id']) ? (int)$queryParams['store_id'] : null;

        $pagination = $this->paginationService->getPaginationParams($request);
        $page = $pagination['page'];
        $limit = $pagination['limit'];
        $offset = $pagination['offset'];

        // Get total count for pagination
        $total = Product::select()->where('type', '=', $type)->count();

        if ($storeId) {
            $sql = <<<'SQL'
SELECT 
    p.id, p.name, p.type, p.description, p.size, p.cost, p.image_url, p.state, p.featured_ingredients, p.all_ingredients, p.created_at, p.updated_at,
    i.quantity, i.min_quantity, i.max_quantity, i.last_restocked
FROM products p
LEFT JOIN inventory i ON p.id = i.product_id AND i.store_id = :store_id
WHERE p.type = :type
ORDER BY p.name
LIMIT :limit OFFSET :offset
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_OBJ);
        } else {
            // Use optimized query with specific columns
            $products = Product::select(['id', 'name', 'type', 'description', 'size', 'cost', 'image_url', 'state', 'featured_ingredients', 'all_ingredients', 'created_at', 'updated_at'])
                                ->where('type', '=', $type)
                                ->orderBy('name')
                                ->limit($limit, $offset)
                                ->get();
        }

        $result = $this->paginationService->formatPaginatedResponse($products, $total, $page, $limit);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/products/types",
     *     summary="Get unique product types",
     *     @OA\Response(
     *         response=200,
     *         description="List of unique product types",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function getUniqueTypes(Request $request, Response $response): Response
    {
        $products = Product::select(['type'])
                            ->groupBy('type')
                            ->orderBy('type')
                            ->get();

        $types = [];
        foreach ($products as $product) {
            if (!empty($product->type)) {
                $types[] = $product->type;
            }
        }

        return $this->success($response, ['data' => array_values(array_unique($types))]);
    }

    /**
     * @OA\Get(
     *     path="/products/{id}",
     *     summary="Get product by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product details",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        // Use cached service for better performance
        $product = $this->cacheableProductService->getProduct($id);

        if (!$product) {
            return $this->error($response, 'Product not found', 404);
        }

        return $this->success($response, $product);
    }

    /**
     * @OA\Post(
     *     path="/products",
     *     summary="Create a new product",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "cost"},
     *             @OA\Property(property="name", type="string", example="New Product"),
     *             @OA\Property(property="cost", type="number", format="float", example=29.99),
     *             @OA\Property(property="type", type="string", example="food"),
     *             @OA\Property(property="description", type="string", example="Product description"),
     *             @OA\Property(property="size", type="string", example="500ml"),
     *             @OA\Property(property="image_url", type="string", example="http://example.com/image.jpg"),
     *             @OA\Property(property="state", type="string", enum={"For Sale", "Discontinued"}, example="For Sale"),
     *             @OA\Property(property="featured_ingredients", type="string"),
     *             @OA\Property(property="all_ingredients", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Convert cost to float if it's a string
        if (isset($data['cost'])) {
            if (is_string($data['cost'])) {
                $data['cost'] = (float)$data['cost'];
            } elseif (!is_float($data['cost']) && !is_int($data['cost'])) {
                return $this->error($response, 'Cost must be a valid number', 400);
            }
        }

        $validator = v::key('name', v::stringType()->notEmpty())
                      ->key('cost', v::numericVal()->positive())
                      ->key('image_url', v::url(), false)
                      ->key('state', v::in(['For Sale', 'Discontinued']), false);

        try {
            $validator->assert($data);
            // Process valid data
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages();
            return $this->error($response, reset($errors), 400);
        }

        $product = new Product();
        $product->name = $data['name'];
        $product->type = $data['type'] ?? null;
        $product->description = $data['description'] ?? null;
        $product->featured_ingredients = $data['featured_ingredients'] ?? null;
        $product->all_ingredients = $data['all_ingredients'] ?? null;
        $product->size = $data['size'] ?? null;
        $product->cost = $data['cost'] ?? null;
        $product->image_url = $data['image_url'] ?? null;
        $product->state = $data['state'] ?? 'For Sale';
        $product->save();

        $this->logger->info('Product created', [
            'product_id' => $product->id,
            'user_id' => $request->getAttribute('user_id')
        ]);

        return $this->success($response, $product, 201);
    }

    /**
     * @OA\Put(
     *     path="/products/{id}",
     *     summary="Update a product",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="cost", type="number", format="float"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="size", type="string"),
     *             @OA\Property(property="image_url", type="string"),
     *             @OA\Property(property="state", type="string", enum={"For Sale", "Discontinued"}),
     *             @OA\Property(property="featured_ingredients", type="string"),
     *             @OA\Property(property="all_ingredients", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        // Convert cost to float if it's a string
        if (isset($data['cost'])) {
            if (is_string($data['cost'])) {
                $data['cost'] = (float)$data['cost'];
            } elseif (!is_float($data['cost']) && !is_int($data['cost'])) {
                return $this->error($response, 'Cost must be a valid number', 400);
            }
        }

        $validator = v::key('name', v::stringType()->notEmpty())
                      ->key('cost', v::numericVal()->positive(), false) // optional
                      ->key('image_url', v::url(), false)
                      ->key('state', v::in(['For Sale', 'Discontinued']), false);

        try {
            $validator->assert($data);
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages();
            return $this->error($response, reset($errors), 400);
        }

        $product = Product::find($id);
        if (!$product) {
            return $this->error($response, 'Product not found', 404);
        }

        $product->name = $data['name'];
        $product->type = $data['type'] ?? $product->type;
        $product->description = $data['description'] ?? $product->description;
        $product->featured_ingredients = $data['featured_ingredients'] ?? $product->featured_ingredients;
        $product->all_ingredients = $data['all_ingredients'] ?? $product->all_ingredients;
        $product->size = $data['size'] ?? $product->size;
        $product->cost = $data['cost'] ?? $product->cost;
        $product->image_url = $data['image_url'] ?? $product->image_url;
        $product->state = $data['state'] ?? $product->state;
        $product->save();

        // Invalidate cache
        $this->cacheableProductService->invalidateProduct($id);

        return $this->success($response, $product);
    }

    /**
     * @OA\Delete(
     *     path="/products/{id}",
     *     summary="Delete a product",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Product deleted"
     *     )
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $product = Product::find($id);
        if ($product) {
            $product->delete();
            // Invalidate cache
            $this->cacheableProductService->invalidateProduct($id);
        }

        return $response->withStatus(204);
    }

    /**
     * @OA\Post(
     *     path="/products/{id}/upload-image",
     *     summary="Upload product image",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="file", type="object")
     *         )
     *     )
     * )
     */
    public function uploadImage(Request $request, Response $response, array $args): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['image'])) {
            return $this->error($response, 'No image uploaded', 400);
        }

        $image = $uploadedFiles['image'];

        // Get the file upload service from middleware
        $fileUploadService = FileUploadMiddleware::getFileUploadService($request);

        try {
            $result = $fileUploadService->uploadFile($image, 'image', [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif'],
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
                'max_file_size' => 2 * 1024 * 1024, // 2MB for images
                'create_thumbnails' => true,
                'thumbnail_size' => [200, 200]
            ]);

            // Optionally save image URL to product
            $productId = (int)$args['id'];
            $product = Product::find($productId);
            if ($product) {
                $product->image_url = $result['url'];
                $product->save();
                $this->cacheableProductService->invalidateProduct($productId);
            }

            return $this->success($response, [
                'message' => 'Image uploaded successfully',
                'file' => $result
            ]);

        } catch (\Exception $e) {
            return $this->error($response, 'Upload failed: ' . $e->getMessage(), 400);
        }
    }
}
