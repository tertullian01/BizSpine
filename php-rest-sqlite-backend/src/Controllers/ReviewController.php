<?php
namespace App\Controllers;

use App\Models\ProductReview;
use App\Models\Product;
use App\Models\Order;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReviewController
{
    public function getAll(Request $request, Response $response): Response
    {
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
SQL;
        
        $reviews = ProductReview::fetchAll($sql);
        $response->getBody()->write(json_encode($reviews));
        return $response->withHeader('Content-Type', 'application/json');
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
        
        $reviews = ProductReview::fetchAll($sql, [':product_id' => $productId]);
        $response->getBody()->write(json_encode($reviews));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getMyReviews(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
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
        
        $reviews = ProductReview::fetchAll($sql, [':user_id' => $userId]);
        $response->getBody()->write(json_encode($reviews));
        return $response->withHeader('Content-Type', 'application/json');
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
        
        $review = ProductReview::fetchOne($sql, [':id' => $id]);
        
        if (!$review) {
            $response->getBody()->write(json_encode(['error' => 'Review not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($review));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        try {
            $review = ProductReview::createReview($body, $userId);
            return $this->getById($request, $response->withStatus(201), ['id' => $review->id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $review = ProductReview::find($id);
        
        if (!$review) {
            $response->getBody()->write(json_encode(['error' => 'Review not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($review->user_id != $userId) {
            $response->getBody()->write(json_encode(['error' => 'You can only update your own reviews']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        try {
            $review->updateReview($body);
            return $this->getById($request, $response, ['id' => $id]);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $review = ProductReview::find($id);
        if (!$review) {
            $response->getBody()->write(json_encode(['error' => 'Review not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $review->publish();
        
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function unpublish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $review = ProductReview::find($id);
        if (!$review) {
            $response->getBody()->write(json_encode(['error' => 'Review not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $review->unpublish();
        
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $review = ProductReview::find($id);
        
        if (!$review) {
            $response->getBody()->write(json_encode(['error' => 'Review not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($review->user_id != $userId) {
            $response->getBody()->write(json_encode(['error' => 'You can only delete your own reviews']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        $review->delete();
        
        return $response->withStatus(204);
    }
}