<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Testimonial;
use App\Services\Database;
use App\Services\FileUploadService;
use App\Services\PaginationService;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Testimonial",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_name", type="string", example="Jane Doe"),
 *     @OA\Property(property="customer_email", type="string", example="jane@example.com"),
 *     @OA\Property(property="testimonial_text", type="string", example="Great product!"),
 *     @OA\Property(property="age_range", type="string", example="25-34"),
 *     @OA\Property(property="image_url", type="string", example="http://example.com/photo.jpg"),
 *     @OA\Property(property="published", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TestimonialController extends ApiController
{
    private PDO $db;
    private Validator $validator;
    private PaginationService $paginationService;
    private FileUploadService $fileUploadService;

    public function __construct(PDO $db = null, PaginationService $paginationService = null, ?FileUploadService $fileUploadService = null)
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
        $this->fileUploadService = $fileUploadService ?? new FileUploadService(new \App\Services\Logger());
    }

    /**
     * @OA\Get(
     *     path="/testimonials/published",
     *     summary="Get published testimonials",
     *     @OA\Response(
     *         response=200,
     *         description="List of published testimonials",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Testimonial")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getPublished(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = Testimonial::select()->where('published', '=', 1)->count();

        $testimonials = Testimonial::select(['id', 'customer_name', 'customer_email', 'testimonial_text', 'age_range', 'published', 'created_at'])
                                  ->where('published', '=', 1)
                                  ->orderBy('created_at', 'DESC')
                                  ->limit($pagination['limit'], $pagination['offset'])
                                  ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/testimonials",
     *     summary="Get all testimonials",
     *     @OA\Response(
     *         response=200,
     *         description="List of all testimonials",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Testimonial")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = Testimonial::select()->where('published', '=', 1)->count();

        // Include all columns with ordering
        $testimonials = Testimonial::select(['*'])
                                  ->where('published', '=', 1)
                                  ->orderBy('created_at', 'DESC')
                                  ->limit($pagination['limit'], $pagination['offset'])
                                  ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/testimonials/admin",
     *     summary="Get all testimonials (Admin)",
     *     @OA\Response(
     *         response=200,
     *         description="List of all testimonials",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Testimonial")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getAllAdmin(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = Testimonial::count();

        $testimonials = Testimonial::select(['*'])
                                  ->orderBy('created_at', 'DESC')
                                  ->limit($pagination['limit'], $pagination['offset'])
                                  ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/testimonials/featured",
     *     summary="Get featured testimonials",
     *     tags={"Testimonials"},
     *     @OA\Response(
     *         response=200,
     *         description="List of featured testimonials",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Testimonial")),
     *                 @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *             )
     *         )
     *     )
     * )
     */
    public function getFeatured(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = Testimonial::select()
            ->where('published', '=', 1)
            ->where('is_featured', '=', 1)
            ->count();

        $testimonials = Testimonial::select(['*'])
            ->where('published', '=', 1)
            ->where('is_featured', '=', 1)
            ->orderBy('created_at', 'DESC')
            ->limit($pagination['limit'], $pagination['offset'])
            ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }

    /**
     * @OA\Get(
     *     path="/testimonials/{id}",
     *     summary="Get testimonial by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Testimonial details",
     *         @OA\JsonContent(ref="#/components/schemas/Testimonial")
     *     )
     * )
     */
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $stmt = $this->db->prepare('SELECT * FROM testimonials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $testimonial = $stmt->fetchObject('App\Models\Testimonial');
        if (!$testimonial) {
            return $this->error($response, 'Testimonial not found', 404);
        }

        return $this->success($response, $testimonial);
    }

    /**
     * @OA\Post(
     *     path="/testimonials",
     *     summary="Create a new testimonial",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_name", "testimonial_text"},
     *             @OA\Property(property="customer_name", type="string"),
     *             @OA\Property(property="testimonial_text", type="string"),
     *             @OA\Property(property="customer_email", type="string"),
     *             @OA\Property(property="age_range", type="string"),
     *             @OA\Property(property="image_url", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Testimonial created", @OA\JsonContent(ref="#/components/schemas/Testimonial"))
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        // Custom validation for testimonial creation
        // Required fields: customer_name, testimonial_text
        // Optional fields: customer_email, age_range, image_url
        if (!isset($body['customer_name']) || empty(trim($body['customer_name']))) {
            return $this->error($response, 'Customer Name is required', 400);
        }

        if (!isset($body['testimonial_text']) || empty(trim($body['testimonial_text']))) {
            return $this->error($response, 'Testimonial Text is required', 400);
        }

        // Validate email format only if provided
        if (isset($body['customer_email']) && !empty($body['customer_email']) && !filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'Invalid email format', 400);
        }

        // Validate age range
        $validAgeRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
        if (isset($body['age_range']) && !empty($body['age_range']) && !in_array($body['age_range'], $validAgeRanges)) {
            return $this->error($response, 'Invalid age range', 400);
        }

        try {
            $sql = <<<'SQL'
INSERT INTO testimonials
    (customer_name, customer_email, age_range, testimonial_text, image_url, published, created_at, updated_at)
VALUES
    (:customer_name, :customer_email, :age_range, :testimonial_text, :image_url, 0, datetime("now"), datetime("now"))
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':customer_name' => $body['customer_name'],
                ':customer_email' => $body['customer_email'] ?? '',
                ':age_range' => $body['age_range'] ?? null,
                ':testimonial_text' => $body['testimonial_text'],
                ':image_url' => $body['image_url'] ?? null,
            ]);
            $id = (int)$this->db->lastInsertId();
            return $this->getById($request, $response->withStatus(201), ['id' => $id]);
        } catch (\PDOException $e) {
            return $this->internalError($response);
        }
    }

    /**
     * @OA\Put(
     *     path="/testimonials/{id}",
     *     summary="Update a testimonial",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_name", type="string"),
     *             @OA\Property(property="testimonial_text", type="string"),
     *             @OA\Property(property="customer_email", type="string"),
     *             @OA\Property(property="age_range", type="string"),
     *             @OA\Property(property="image_url", type="string"),
     *             @OA\Property(property="published", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Testimonial updated", @OA\JsonContent(ref="#/components/schemas/Testimonial"))
     * )
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Testimonial not found', 404);
        }

        // Manually validate email if present and not empty
        if (array_key_exists('customer_email', $body) && !empty($body['customer_email']) && !v::email()->validate($body['customer_email'])) {
            return $this->error($response, 'Customer Email must be a valid email address', 400);
        }

        // Manually validate image_url if present and not empty
        if (isset($body['image_url']) && !empty($body['image_url']) && !v::url()->validate($body['image_url'])) {
            return $this->error($response, 'Image URL must be a valid URL', 400);
        }

        $updates = [];
        $params = [':id' => $id];
        if (isset($body['customer_name'])) {
            $updates[] = 'customer_name = :customer_name';
            $params[':customer_name'] = $body['customer_name'];
        }

        if (array_key_exists('customer_email', $body)) {
            $updates[] = 'customer_email = :customer_email';
            $params[':customer_email'] = $body['customer_email'] ?? '';
        }

        if (isset($body['age_range'])) {
            $updates[] = 'age_range = :age_range';
            $params[':age_range'] = $body['age_range'];
        }

        if (isset($body['testimonial_text'])) {
            $updates[] = 'testimonial_text = :testimonial_text';
            $params[':testimonial_text'] = $body['testimonial_text'];
        }

        if (isset($body['image_url'])) {
            $updates[] = 'image_url = :image_url';
            $params[':image_url'] = $body['image_url'];
        }

        if (isset($body['published'])) {
            $updates[] = 'published = :published';
            $params[':published'] = filter_var($body['published'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($updates)) {
            throw new ValidationException('No valid fields to update');
        }

        $updates[] = 'updated_at = datetime("now")';
        $sql = 'UPDATE testimonials SET ' . implode(', ', $updates) . ' WHERE id = :id';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\PDOException $e) {
            return $this->internalError($response);
        }
    }

    /**
     * @OA\Post(
     *     path="/testimonials/{id}/publish",
     *     summary="Publish a testimonial",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Testimonial published", @OA\JsonContent(ref="#/components/schemas/Testimonial"))
     * )
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Testimonial not found', 404);
        }

        $stmt = $this->db->prepare('UPDATE testimonials SET published = 1, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    /**
     * @OA\Post(
     *     path="/testimonials/{id}/unpublish",
     *     summary="Unpublish a testimonial",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Testimonial unpublished", @OA\JsonContent(ref="#/components/schemas/Testimonial"))
     * )
     */
    public function unpublish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Testimonial not found', 404);
        }

        $stmt = $this->db->prepare('UPDATE testimonials SET published = 0, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    /**
     * @OA\Delete(
     *     path="/testimonials/{id}",
     *     summary="Delete a testimonial",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Testimonial deleted")
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Testimonial not found', 404);
        }

        $stmt = $this->db->prepare('DELETE FROM testimonials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $response->withStatus(204);
    }

    /**
     * @OA\Post(
     *     path="/testimonials/{id}/upload-photo",
     *     summary="Upload testimonial photo",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(@OA\Property(property="photo", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Photo uploaded")
     * )
     */
    public function uploadPhoto(Request $request, Response $response, array $args): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['photo'])) {
            return $this->error($response, 'No photo uploaded', 400);
        }

        $photo = $uploadedFiles['photo'];

        try {
            $result = $this->fileUploadService->uploadFile($photo, 'photo', [
                'allowed_extensions' => ['jpg', 'jpeg', 'png'],
                'allowed_mime_types' => ['image/jpeg', 'image/png'],
                'max_file_size' => 2 * 1024 * 1024, // 2MB for photos
                'create_thumbnails' => true,
                'thumbnail_size' => [100, 100]
            ]);

            // Save photo URL to testimonial
            $testimonialId = (int)$args['id'];
            $stmt = $this->db->prepare('UPDATE testimonials SET image_url = :url WHERE id = :id');
            $stmt->execute([
                ':url' => $result['url'],
                ':id' => $testimonialId
            ]);

            return $this->success($response, [
                'message' => 'Photo uploaded successfully',
                'file' => $result
            ]);

        } catch (\Exception $e) {
            return $this->error($response, 'Upload failed: ' . $e->getMessage(), 400);
        }
    }
}
