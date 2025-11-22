<?php

namespace App\Controllers;

use App\Models\Product;
use App\Services\CacheableProductService;
use App\Services\Logger;
use App\Services\PaginationService;
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
class ProductController
{
    private CacheableProductService $cacheableProductService;
    private Logger $logger;
    private PaginationService $paginationService;

    public function __construct(CacheableProductService $cacheableProductService, Logger $logger, PaginationService $paginationService)
    {
        $this->cacheableProductService = $cacheableProductService;
        $this->logger = $logger;
        $this->paginationService = $paginationService;
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
        $products = Product::select(['id', 'name', 'type', 'cost', 'created_at', 'updated_at'])
                           ->orderBy('name')
                           ->limit($limit, $offset)
                           ->get();

        $result = $this->paginationService->formatPaginatedResponse($products, $total, $page, $limit);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
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
     *             @OA\Property(property="description", type="string", example="Product description")
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

        $validator = v::key('name', v::stringType()->notEmpty())
                     ->key('cost', v::floatType()->positive());

        try {
            $validator->assert($data);
            // Process valid data
        } catch (NestedValidationException $e) {
            $response->getBody()->write(json_encode(['errors' => $e->getMessages()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $product = new Product();
        $product->name = $data['name'];
        $product->type = $data['type'] ?? null;
        $product->description = $data['description'] ?? null;
        $product->featured_ingredients = $data['featured_ingredients'] ?? null;
        $product->all_ingredients = $data['all_ingredients'] ?? null;
        $product->size = $data['size'] ?? null;
        $product->cost = $data['cost'] ?? null;
        $product->save();

        $this->logger->info('Product created', [
            'product_id' => $product->id,
            'user_id' => $request->getAttribute('user_id')
        ]);

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        $validator = v::key('name', v::stringType()->notEmpty())
                     ->key('cost', v::floatType()->positive(), false); // optional

        try {
            $validator->assert($data);
        } catch (NestedValidationException $e) {
            $response->getBody()->write(json_encode(['errors' => $e->getMessages()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $product = Product::find($id);
        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $product->name = $data['name'];
        $product->type = $data['type'] ?? $product->type;
        $product->description = $data['description'] ?? $product->description;
        $product->featured_ingredients = $data['featured_ingredients'] ?? $product->featured_ingredients;
        $product->all_ingredients = $data['all_ingredients'] ?? $product->all_ingredients;
        $product->size = $data['size'] ?? $product->size;
        $product->cost = $data['cost'] ?? $product->cost;
        $product->save();

        // Invalidate cache
        $this->cacheableProductService->invalidateProduct($id);

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

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
}
