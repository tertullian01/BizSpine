<?php
namespace App\Controllers;

use App\Models\Coupon;
use App\Models\CouponUsage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CouponController
{
    public function getAll(Request $request, Response $response): Response
    {
        $coupons = Coupon::findAll();
        $response->getBody()->write(json_encode($coupons));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $coupon = Coupon::find($id);
        
        if (!$coupon) {
            $response->getBody()->write(json_encode(['error' => 'Coupon not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($coupon));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        if (empty($body['code']) || empty($body['discount_type']) || !isset($body['discount_value'])) {
            $response->getBody()->write(json_encode(['error' => 'code, discount_type, and discount_value are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        if (!in_array($body['discount_type'], ['percentage', 'fixed'])) {
            $response->getBody()->write(json_encode(['error' => 'discount_type must be "percentage" or "fixed"']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $coupon = new Coupon([
                'code' => strtoupper($body['code']),
                'discount_type' => $body['discount_type'],
                'discount_value' => (float)$body['discount_value'],
                'min_purchase_amount' => $body['min_purchase_amount'] ?? 0,
                'max_uses' => $body['max_uses'] ?? null,
                'valid_from' => $body['valid_from'] ?? null,
                'valid_until' => $body['valid_until'] ?? null,
                'is_active' => $body['is_active'] ?? 1,
                'description' => $body['description'] ?? null,
            ]);
            $coupon->save();
            
            return $this->getById($request, $response->withStatus(201), ['id' => $coupon->id]);
            
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                $response->getBody()->write(json_encode(['error' => 'Coupon code already exists']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            } else {
                $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $coupon = Coupon::find($id);
        if (!$coupon) {
            $response->getBody()->write(json_encode(['error' => 'Coupon not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $coupon->delete();
        
        return $response->withStatus(204);
    }

    public function getUsageReport(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    cu.*,
    c.code as coupon_code,
    u.email as user_email,
    o.order_number
FROM coupon_usage cu
LEFT JOIN coupons c ON cu.coupon_id = c.id
LEFT JOIN users u ON cu.user_id = u.id
LEFT JOIN orders o ON cu.order_id = o.id
ORDER BY cu.used_at DESC
SQL;
        
        $usage = CouponUsage::fetchAll($sql);
        $response->getBody()->write(json_encode($usage));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function validateCoupon(string $code, float $orderTotal, int $userId, int $orderId): array
    {
        $coupon = Coupon::fetchOne('SELECT * FROM coupons WHERE code = :code', [':code' => strtoupper($code)]);
        
        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid or inactive coupon code'];
        }

        $validationResult = $coupon->validate($orderTotal, $userId);

        if ($validationResult['valid']) {
            $coupon->recordUsage($userId, $orderId, $validationResult['discount_amount']);
        }

        return $validationResult;
    }
}