<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\ReviewController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ReviewControllerTest extends DatabaseTestCase
{
    private int $userId;
    private int $productId;
    private int $orderId;
    protected function setUp(): void
    {
        parent::setUp();
    // Insert test data
        self::$db->exec("INSERT INTO users (email, password_hash) VALUES ('test@example.com', 'hash')");
        $this->userId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Test Product', 29.99)");
        $this->productId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO stores (name) VALUES ('Siedlung')");
        $storeId = (int)self::$db->lastInsertId();
    // Create a delivered order for purchase verification
        self::$db->exec("INSERT INTO orders (user_id, order_number, shipping_address, fulfillment_status, total) VALUES ({$this->userId}, 'ORD-001', '123 Test St', 'delivered', 29.99)");
        $this->orderId = (int)self::$db->lastInsertId();
        self::$db->exec("INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal) VALUES ({$this->orderId}, {$this->productId}, {$storeId}, 1, 29.99, 29.99)");
    }

    public function testCreateVerifiedReview()
    {
        $controller = new ReviewController(self::$db);
        $request = $this->createRequestWithBody('POST', '/reviews', [
            'product_id' => $this->productId,
            'rating' => 5,
            'review_text' => 'Great product!',
        ]);
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(5, $data['rating']);
        $this->assertEquals('Great product!', $data['review_text']);
        $this->assertEquals(1, $data['verified']);
// Should be verified since user purchased
        $this->assertEquals(0, $data['published']);
// Should not be published by default
    }

    public function testCreateUnverifiedReview()
    {
        // Create a new product that user hasn't purchased
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Unpurchased Product', 19.99)");
        $unpurchasedProductId = (int)self::$db->lastInsertId();
        $controller = new ReviewController(self::$db);
        $request = $this->createRequestWithBody('POST', '/reviews', [
            'product_id' => $unpurchasedProductId,
            'rating' => 4,
            'review_text' => 'Looks good',
        ]);
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(0, $data['verified']);
// Should not be verified
        $this->assertEquals(0, $data['published']);
    }

    public function testGetPublishedReviews()
    {
        // Insert published and unpublished reviews
        self::$db->exec("INSERT INTO product_reviews (user_id, product_id, rating, review_text, verified, published) VALUES ({$this->userId}, {$this->productId}, 5, 'Published review', 1, 1)");
        self::$db->exec("INSERT INTO product_reviews (user_id, product_id, rating, review_text, verified, published) VALUES ({$this->userId}, {$this->productId}, 3, 'Unpublished review', 1, 0)");
        $controller = new ReviewController(self::$db);
        $request = $this->createRequest('GET', '/reviews');
        $response = $this->createResponse();
        $response = $controller->getAll($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        // Only published review
        $this->assertEquals('Published review', $data['data'][0]['review_text']);
    }

    public function testPublishReview()
    {
        self::$db->exec("INSERT INTO product_reviews (user_id, product_id, rating, verified, published) VALUES ({$this->userId}, {$this->productId}, 4, 1, 0)");
        $reviewId = (int)self::$db->lastInsertId();
        $controller = new ReviewController(self::$db);
        $request = $this->createRequest('POST', "/reviews/$reviewId/publish");
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->publish($request, $response, ['id' => $reviewId]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['published']);
    }

    public function testDeleteOwnReview()
    {
        self::$db->exec("INSERT INTO product_reviews (user_id, product_id, rating) VALUES ({$this->userId}, {$this->productId}, 4)");
        $reviewId = (int)self::$db->lastInsertId();
        $controller = new ReviewController(self::$db);
        $request = $this->createRequest('DELETE', "/reviews/$reviewId");
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => $reviewId]);
        $this->assertEquals(204, $response->getStatusCode());
        $stmt = self::$db->query("SELECT COUNT(*) FROM product_reviews WHERE id = $reviewId");
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testCannotDeleteOthersReview()
    {
        // Create another user
        self::$db->exec("INSERT INTO users (email, password_hash) VALUES ('other@example.com', 'hash')");
        $otherUserId = (int)self::$db->lastInsertId();
// Create review by other user
        self::$db->exec("INSERT INTO product_reviews (user_id, product_id, rating) VALUES ($otherUserId, {$this->productId}, 4)");
        $reviewId = (int)self::$db->lastInsertId();
        $controller = new ReviewController(self::$db);
        $request = $this->createRequest('DELETE', "/reviews/$reviewId");
        $request = $request->withAttribute('user_id', $this->userId);
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => $reviewId]);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('You can only delete your own reviews', $data['error']);
    }
}
