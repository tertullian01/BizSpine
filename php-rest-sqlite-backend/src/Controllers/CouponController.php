<?php

namespace App\Controllers;

use App\Services\Config;
use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class CouponController extends ApiController
{
    private PDO $db;
    private Validator $validator;
    public function __construct(?PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = Database::get(Config::getInstance()->get('database.database_path'));
        }
        $this->validator = new Validator();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $stmt = $this->db->query('SELECT * FROM coupons ORDER BY created_at DESC');
        $coupons = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Coupon');
        return $this->success($response, $coupons);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $coupon = $stmt->fetchObject('App\Models\Coupon');
        if (!$coupon) {
            return $this->error($response, 'Coupon not found', 404);
        }

        return $this->success($response, $coupon);
    }

    public function getByCode(Request $request, Response $response, array $args): Response
    {
        $code = $args['code'];
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE UPPER(code) = :code');
        $stmt->execute([':code' => strtoupper($code)]);
        $coupon = $stmt->fetchObject('App\Models\Coupon');
        if (!$coupon) {
            return $this->error($response, "Coupon '{$code}' not found", 404);
        }

        return $this->success($response, $coupon);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Preprocess body to handle aliases and legacy fields
        if (isset($body['discount_type']) && $body['discount_type'] === 'percent') {
            $body['discount_type'] = 'percentage';
        }
        if (isset($body['expiration_date'])) {
            $body['valid_until'] = $body['expiration_date'] === '' ? null : $body['expiration_date'];
        }
        if (isset($body['active'])) {
            $body['is_active'] = (int) $body['active'];
        }
        // Ensure empty strings for dates are converted to null
        if (isset($body['valid_from']) && $body['valid_from'] === '') {
            $body['valid_from'] = null;
        }
        if (isset($body['valid_until']) && $body['valid_until'] === '') {
            $body['valid_until'] = null;
        }
        $this->validator->validate($body, [
            'code' => v::notEmpty()->setName('Code'),
            'discount_type' => v::notEmpty()->in(['percentage', 'fixed'])->setName('Discount Type'),
            'discount_value' => v::notEmpty()->floatVal()->positive()->setName('Discount Value'),
        ]);
        try {
            $sql = <<<'SQL'
INSERT INTO coupons 
    (code, discount_type, discount_value, min_purchase_amount, max_uses, valid_from, valid_until, is_active, description, created_at, updated_at) 
VALUES 
    (:code, :discount_type, :discount_value, :min_purchase, :max_uses, :valid_from, :valid_until, :is_active, :description, datetime("now"), datetime("now"))
SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':code' => strtoupper($body['code']),
                ':discount_type' => $body['discount_type'],
                ':discount_value' => (float) $body['discount_value'],
                ':min_purchase' => $body['min_purchase_amount'] ?? 0,
                ':max_uses' => $body['max_uses'] ?? null,
                ':valid_from' => $body['valid_from'] ?? null,
                ':valid_until' => $body['valid_until'] ?? null,
                ':is_active' => $body['is_active'] ?? 1,
                ':description' => $body['description'] ?? null,
            ]);
            $id = (int) $this->db->lastInsertId();
            return $this->getById($request, $response->withStatus(201), ['id' => $id]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return $this->error($response, 'Coupon code already exists', 409);
            } else {
                return $this->internalError($response);
            }
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $body = $request->getParsedBody();

        // Handle case where client sends array-wrapped payload
        if (is_array($body) && isset($body[0]) && is_array($body[0])) {
            $body = $body[0];
        }

        // Preprocess body to handle aliases and legacy fields
        if (isset($body['discount_type']) && $body['discount_type'] === 'percent') {
            $body['discount_type'] = 'percentage';
        }
        if (isset($body['expiration_date'])) {
            $body['valid_until'] = $body['expiration_date'] === '' ? null : $body['expiration_date'];
        }
        if (isset($body['active'])) {
            $body['is_active'] = (int) $body['active'];
        }
        // Ensure empty strings for dates are converted to null
        if (isset($body['valid_from']) && $body['valid_from'] === '') {
            $body['valid_from'] = null;
        }
        if (isset($body['valid_until']) && $body['valid_until'] === '') {
            $body['valid_until'] = null;
        }

        $this->validator->validate($body, [
            'code' => v::optional(v::notEmpty()->setName('Code')),
            'discount_type' => v::optional(v::notEmpty()->in(['percentage', 'fixed'])->setName('Discount Type')),
            'discount_value' => v::optional(v::notEmpty()->floatVal()->positive()->setName('Discount Value')),
        ]);

        $checkStmt = $this->db->prepare('SELECT id FROM coupons WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Coupon not found', 404);
        }

        try {
            $fields = [];
            $params = [':id' => $id];

            if (isset($body['code'])) {
                $fields[] = 'code = :code';
                $params[':code'] = strtoupper($body['code']);
            }
            if (isset($body['discount_type'])) {
                $fields[] = 'discount_type = :discount_type';
                $params[':discount_type'] = $body['discount_type'];
            }
            if (isset($body['discount_value'])) {
                $fields[] = 'discount_value = :discount_value';
                $params[':discount_value'] = (float) $body['discount_value'];
            }
            if (isset($body['min_purchase_amount'])) {
                $fields[] = 'min_purchase_amount = :min_purchase';
                $params[':min_purchase'] = $body['min_purchase_amount'];
            }
            if (isset($body['max_uses'])) {
                $fields[] = 'max_uses = :max_uses';
                $params[':max_uses'] = $body['max_uses'];
            }
            if (array_key_exists('valid_from', $body)) {
                $fields[] = 'valid_from = :valid_from';
                $params[':valid_from'] = $body['valid_from'];
            }
            if (array_key_exists('valid_until', $body) || array_key_exists('expiration_date', $body)) {
                $fields[] = 'valid_until = :valid_until';
                $params[':valid_until'] = $body['valid_until'];
            }
            if (isset($body['is_active']) || array_key_exists('active', $body)) {
                $fields[] = 'is_active = :is_active';
                $params[':is_active'] = $body['is_active'];
            }
            if (isset($body['description'])) {
                $fields[] = 'description = :description';
                $params[':description'] = $body['description'];
            }

            if (empty($fields)) {
                return $this->success($response, ['message' => 'No changes made']);
            }

            $fields[] = 'updated_at = datetime("now")';

            $sql = 'UPDATE coupons SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->getById($request, $response, ['id' => $id]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return $this->error($response, 'Coupon code already exists', 409);
            } else {
                return $this->internalError($response);
            }
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM coupons WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            return $this->error($response, 'Coupon not found', 404);
        }

        $stmt = $this->db->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
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
        $stmt = $this->db->query($sql);
        $usage = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\CouponUsage');
        return $this->success($response, $usage);
    }

    public function validateCoupon(string $code, float $orderTotal, ?int $userId, int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE UPPER(code) = :code AND is_active = 1');
        $stmt->execute([':code' => strtoupper($code)]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid or inactive coupon code'];
        }

        // Check validity dates
        $now = date('Y-m-d H:i:s');
        if ($coupon['valid_from'] && $now < $coupon['valid_from']) {
            return ['valid' => false, 'error' => 'Coupon not yet valid'];
        }
        if ($coupon['valid_until'] && $now > $coupon['valid_until']) {
            return ['valid' => false, 'error' => 'Coupon has expired'];
        }

        // Check max uses
        if ($coupon['max_uses'] && $coupon['times_used'] >= $coupon['max_uses']) {
            return ['valid' => false, 'error' => 'Coupon usage limit reached'];
        }

        // Check minimum purchase
        if ($orderTotal < $coupon['min_purchase_amount']) {
            return ['valid' => false, 'error' => "Minimum purchase of {$coupon['min_purchase_amount']} required"];
        }

        // Calculate discount
        $discountAmount = 0;
        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = $orderTotal * ($coupon['discount_value'] / 100);
        } else {
            $discountAmount = $coupon['discount_value'];
        }

        // Record usage
        try {
            if ($userId) {
                $usageSql = 'INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount, used_at) VALUES (:coupon_id, :user_id, :order_id, :discount, datetime("now"))';
                $usageStmt = $this->db->prepare($usageSql);
                $usageStmt->execute([
                    ':coupon_id' => $coupon['id'],
                    ':user_id' => $userId,
                    ':order_id' => $orderId,
                    ':discount' => $discountAmount,
                ]);
            }
            // Update coupon times_used
            $updateSql = 'UPDATE coupons SET times_used = times_used + 1, updated_at = datetime("now") WHERE id = :id';
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':id' => $coupon['id']]);
            return ['valid' => true, 'discount_amount' => $discountAmount, 'coupon_id' => $coupon['id']];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Error applying coupon: ' . $e->getMessage()];
        }
    }
}
