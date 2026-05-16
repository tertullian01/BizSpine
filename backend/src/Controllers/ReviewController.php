<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\PaginationService;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class ReviewController extends ApiController
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
        $countStmt = $this->db->query('SELECT COUNT(*) as total FROM product_reviews WHERE published = 1');
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = <<<'SQL'
SELECT
    r.*,
    u.email as user_email,
    p.name as product_name
FROM product_reviews r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN products p ON r.product_id = p.id
WHERE r.published = 1
ORDER BY r.created_at DESC
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\ProductReview');

        $result = $this->paginationService->formatPaginatedResponse($reviews, $total, $page, $limit);

        return $this->success($response, $result);
    }

    public function getByProduct(Request $request, Response $response, array $args): Response
    {
        $productId = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email,
    p.name as product_name
FROM product_reviews r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN products p ON r.product_id = p.id
WHERE r.product_id = :product_id AND r.published = 1
ORDER BY r.created_at DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':product_id' => $productId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\ProductReview');
        return $this->success($response, $reviews);
    }

    public function getMyReviews(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email,
    p.name as product_name
FROM product_reviews r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN products p ON r.product_id = p.id
WHERE r.user_id = :user_id
ORDER BY r.created_at DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\ProductReview');
        return $this->success($response, $reviews);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email,
    p.name as product_name
FROM product_reviews r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN products p ON r.product_id = p.id
WHERE r.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $review = $stmt->fetchObject('App\Models\ProductReview');
        if (!$review) {
            return $this->error($response, 'Review not found', 404);
        }

        return $this->success($response, $review);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        try {
            $this->validator->validate($body, [
                'product_id' => v::notEmpty()->intVal()->setName('Product ID'),
                'rating' => v::notEmpty()->intVal()->between(1, 5)->setName('Rating'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        }

        $productId = (int)$body['product_id'];
        $rating = (int)$body['rating'];
        $stmt = $this->db->prepare('SELECT id FROM products WHERE id = :id');
        $stmt->execute([':id' => $productId]);
        if (!$stmt->fetch()) {
            return $this->error($response, 'Product not found', 404);
        }

        $purchaseCheckSql = <<<'SQL'
SELECT o.id as order_id
FROM orders o
INNER JOIN order_items oi ON o.id = oi.order_id
WHERE o.user_id = :user_id 
  AND oi.product_id = :product_id
  AND o.fulfillment_status IN ('shipped', 'delivered')
LIMIT 1
SQL;
        $stmt = $this->db->prepare($purchaseCheckSql);
        $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        $verified = $purchase ? 1 : 0;
        $orderId = $purchase ? $purchase['order_id'] : null;
        try {
            $sql = <<<'SQL'
INSERT INTO product_reviews 
    (user_id, product_id, order_id, rating, review_text, verified, published, created_at, updated_at) 
VALUES 
    (:user_id, :product_id, :order_id, :rating, :review_text, :verified, 0, datetime("now"), datetime("now"))
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':product_id' => $productId,
                ':order_id' => $orderId,
                ':rating' => $rating,
                ':review_text' => $body['review_text'] ?? null,
                ':verified' => $verified,
            ]);
            $id = (int)$this->db->lastInsertId();
            return $this->getById($request, $response->withStatus(201), ['id' => $id]);
        } catch (\PDOException $e) {
            return $this->internalError($response);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $checkStmt = $this->db->prepare('SELECT user_id, published FROM product_reviews WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        $review = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            return $this->error($response, 'Review not found', 404);
        }

        if ($review['user_id'] != $userId) {
            return $this->error($response, 'You can only update your own reviews', 403);
        }

        if ($review['published'] == 1) {
            return $this->error($response, 'Cannot update published reviews', 400);
        }

        try {
            $this->validator->validate($body, [
                'rating' => v::optional(v::intVal()->between(1, 5)->setName('Rating')),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        }

        $updates = [];
        $params = [':id' => $id];
        if (isset($body['rating'])) {
            $updates[] = 'rating = :rating';
            $params[':rating'] = (int)$body['rating'];
        }

        if (isset($body['review_text'])) {
            $updates[] = 'review_text = :review_text';
            $params[':review_text'] = $body['review_text'];
        }

        if (empty($updates)) {
            return $this->error($response, 'No valid fields to update', 400);
        }

        $updates[] = 'updated_at = datetime("now")';
        $sql = 'UPDATE product_reviews SET ' . implode(', ', $updates) . ' WHERE id = :id';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\PDOException $e) {
            return $this->internalError($response);
        }
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM product_reviews WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Review not found', 404);
        }

        $stmt = $this->db->prepare('UPDATE product_reviews SET published = 1, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function unpublish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM product_reviews WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Review not found', 404);
        }

        $stmt = $this->db->prepare('UPDATE product_reviews SET published = 0, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $checkStmt = $this->db->prepare('SELECT user_id FROM product_reviews WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        $review = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            return $this->error($response, 'Review not found', 404);
        }

        if ($review['user_id'] != $userId) {
            return $this->error($response, 'You can only delete your own reviews', 403);
        }

        $stmt = $this->db->prepare('DELETE FROM product_reviews WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $response->withStatus(204);
    }
}
