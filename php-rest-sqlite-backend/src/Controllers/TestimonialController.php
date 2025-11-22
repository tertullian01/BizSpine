<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Testimonial;
use App\Services\Database;
use App\Services\PaginationService;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class TestimonialController
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
        $total = Testimonial::select()->where('published', '=', 1)->count();

        // Use optimized query with specific columns, including testimonial_text for API responses
        $testimonials = Testimonial::select(['id', 'customer_name', 'customer_email', 'testimonial_text', 'age_range', 'published', 'created_at'])
                                  ->where('published', '=', 1)
                                  ->orderBy('created_at', 'DESC')
                                  ->limit($pagination['limit'], $pagination['offset'])
                                  ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getAllAdmin(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = Testimonial::count();

        // Use optimized query for admin view - include all columns but with ordering
        $testimonials = Testimonial::select(['*'])
                                  ->orderBy('created_at', 'DESC')
                                  ->limit($pagination['limit'], $pagination['offset'])
                                  ->get();

        $result = $this->paginationService->formatPaginatedResponse($testimonials, $total, $pagination['page'], $pagination['limit']);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $stmt = $this->db->prepare('SELECT * FROM testimonials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $testimonial = $stmt->fetchObject('App\Models\Testimonial');
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($testimonial));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $validAgeRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
// Custom validation for testimonial creation
        if (!isset($body['customer_name']) || empty(trim($body['customer_name']))) {
            $response->getBody()->write(json_encode(['error' => 'Customer Name is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!isset($body['customer_email']) || empty(trim($body['customer_email']))) {
            $response->getBody()->write(json_encode(['error' => 'Customer Email is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid email format']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!isset($body['testimonial_text']) || empty(trim($body['testimonial_text']))) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial Text is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (isset($body['age_range']) && !in_array($body['age_range'], $validAgeRanges)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid age range']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
                ':customer_email' => $body['customer_email'],
                ':age_range' => $body['age_range'] ?? null,
                ':testimonial_text' => $body['testimonial_text'],
                ':image_url' => $body['image_url'] ?? null,
            ]);
            $id = (int)$this->db->lastInsertId();
            return $this->getById($request, $response->withStatus(201), ['id' => $id]);
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $validAgeRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
        try {
            $this->validator->validate($body, [
                'customer_email' => v::optional(v::email()->setName('Customer Email')),
                'age_range' => v::optional(v::in($validAgeRanges)->setName('Age Range')),
                'image_url' => v::optional(v::url()->setName('Image URL')),
            ]);
        } catch (ValidationException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getFirstError()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $updates = [];
        $params = [':id' => $id];
        if (isset($body['customer_name'])) {
            $updates[] = 'customer_name = :customer_name';
            $params[':customer_name'] = $body['customer_name'];
        }

        if (isset($body['customer_email'])) {
            $updates[] = 'customer_email = :customer_email';
            $params[':customer_email'] = $body['customer_email'];
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
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $this->db->prepare('UPDATE testimonials SET published = 1, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function unpublish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $this->db->prepare('UPDATE testimonials SET published = 0, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM testimonials WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $this->db->prepare('DELETE FROM testimonials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $response->withStatus(204);
    }
}
