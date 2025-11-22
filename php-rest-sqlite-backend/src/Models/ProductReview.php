<?php

namespace App\Models;

class ProductReview extends BaseModel
{
    protected static string $tableName = 'product_reviews';
// Additional properties for joined data
    public ?string $user_email;
    public ?string $product_name;
    public static function createReview(array $body, int $userId): ProductReview
    {
        if (empty($body['product_id']) || empty($body['rating'])) {
            throw new \Exception('product_id and rating are required');
        }

        $productId = (int)$body['product_id'];
        $rating = (int)$body['rating'];
        if ($rating < 1 || $rating > 5) {
            throw new \Exception('Rating must be between 1 and 5');
        }

        $product = Product::find($productId);
        if (!$product) {
            throw new \Exception('Product not found');
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
        $purchase = Order::fetchOne($purchaseCheckSql, [':user_id' => $userId, ':product_id' => $productId]);
        $verified = $purchase ? 1 : 0;
        $orderId = $purchase ? $purchase->id : null;
        $review = new ProductReview([
            'user_id' => $userId,
            'product_id' => $productId,
            'order_id' => $orderId,
            'rating' => $rating,
            'review_text' => $body['review_text'] ?? null,
            'verified' => $verified,
            'published' => 0,
        ]);
        $review->save();
        return $review;
    }

    public function updateReview(array $body): void
    {
        if ($this->published == 1) {
            throw new \Exception('Cannot update published reviews');
        }

        if (isset($body['rating'])) {
            $rating = (int)$body['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new \Exception('Rating must be between 1 and 5');
            }
            $this->rating = $rating;
        }

        if (isset($body['review_text'])) {
            $this->review_text = $body['review_text'];
        }

        $this->save();
    }

    public function publish(): void
    {
        $this->published = 1;
        $this->save();
    }

    public function unpublish(): void
    {
        $this->published = 0;
        $this->save();
    }
}
