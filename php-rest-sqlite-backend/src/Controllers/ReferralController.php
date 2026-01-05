<?php

namespace App\Controllers;

use App\Models\UserReferral;
use App\Models\ReferralUsage;
use App\Services\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReferralController extends ApiController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->ensureSchema();
    }

    private const POINTS_PER_REFERRAL = 100;
    public function getAll(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email
FROM user_referrals r
LEFT JOIN users u ON r.user_id = u.id
ORDER BY r.created_at DESC
SQL;
        $stmt = $this->db->query($sql);
        $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->success($response, $referrals);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email
FROM user_referrals r
LEFT JOIN users u ON r.user_id = u.id
WHERE r.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral) {
            return $this->error($response, 'Referral code not found', 404);
        }

        return $this->success($response, $referral);
    }

    public function getByCode(Request $request, Response $response, array $args): Response
    {
        $code = $args['code'];
        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email
FROM user_referrals r
LEFT JOIN users u ON r.user_id = u.id
WHERE r.referral_code = :code
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral) {
            return $this->error($response, 'Referral code not found', 404);
        }

        return $this->success($response, $referral);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['client_id'])) {
            return $this->error($response, 'Client ID is required', 400);
        }

        $userId = (int)$body['client_id'];
        $code = $body['code'] ?? $this->generateReferralCode();
        $discountType = $body['discount_type'] ?? 'percentage';
        $discountAmount = $body['discount_value'] ?? 10.0;
        $status = $body['status'] ?? 'active';

        // Check if referral exists for user
        $stmt = $this->db->prepare('SELECT id FROM user_referrals WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        if ($stmt->fetch()) {
            return $this->error($response, 'Referral code already exists for this user', 409);
        }

        // Check code uniqueness
        $stmt = $this->db->prepare('SELECT id FROM user_referrals WHERE referral_code = :code');
        $stmt->execute([':code' => $code]);
        if ($stmt->fetch()) {
            return $this->error($response, 'Referral code already exists', 409);
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO user_referrals (user_id, referral_code, discount_type, discount_amount, status, created_at, updated_at) VALUES (:user_id, :code, :discount_type, :discount_amount, :status, datetime('now'), datetime('now'))");
            $stmt->execute([
                ':user_id' => $userId,
                ':code' => $code,
                ':discount_type' => $discountType,
                ':discount_amount' => $discountAmount,
                ':status' => $status
            ]);
            
            $id = (int)$this->db->lastInsertId();
            return $this->getById($request, $response->withStatus(201), ['id' => $id]);
        } catch (\PDOException $e) {
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();

        $stmt = $this->db->prepare('SELECT id FROM user_referrals WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) {
            return $this->error($response, 'Referral code not found', 404);
        }

        $updates = [];
        $params = [':id' => $id];

        if (isset($body['referral_code']) && !empty($body['referral_code'])) {
            // Check uniqueness
            $checkStmt = $this->db->prepare('SELECT id FROM user_referrals WHERE referral_code = :code AND id != :id');
            $checkStmt->execute([':code' => $body['referral_code'], ':id' => $id]);
            if ($checkStmt->fetch()) {
                return $this->error($response, 'Referral code already exists', 409);
            }
            $updates[] = 'referral_code = :code';
            $params[':code'] = $body['referral_code'];
        }

        if (isset($body['points_balance'])) {
            $updates[] = 'points_balance = :points_balance';
            $params[':points_balance'] = (int)$body['points_balance'];
        }

        if (isset($body['discount_type'])) {
            $updates[] = 'discount_type = :discount_type';
            $params[':discount_type'] = $body['discount_type'];
        }

        if (isset($body['discount_amount'])) {
            $updates[] = 'discount_amount = :discount_amount';
            $params[':discount_amount'] = (float)$body['discount_amount'];
        }

        if (isset($body['status'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $body['status'];
        }

        if (!empty($updates)) {
            $updates[] = 'updated_at = datetime("now")';
            $sql = 'UPDATE user_referrals SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        return $this->getById($request, $response, ['id' => $id]);
    }

    public function getMyReferral(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $sql = <<<'SQL'
SELECT 
    r.*,
    u.email as user_email
FROM user_referrals r
LEFT JOIN users u ON r.user_id = u.id
WHERE r.user_id = :user_id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$referral) {
            // Create referral code if doesn't exist
            $referral = $this->createReferralRecord($userId);
        }

        return $this->success($response, $referral);
    }

    public function createReferral(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $stmt = $this->db->prepare('SELECT * FROM user_referrals WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($referral) {
            return $this->error($response, 'Referral code already exists', 409);
        }

        $referral = $this->createReferralRecord($userId);
        return $this->success($response, $referral, 201);
    }

    public function deleteReferral(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $stmt = $this->db->prepare('DELETE FROM user_referrals WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'Referral code not found', 404);
        }

        return $this->success($response, ['message' => 'Referral code deleted successfully']);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $stmt = $this->db->prepare('DELETE FROM user_referrals WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'Referral code not found', 404);
        }

        return $this->success($response, ['message' => 'Referral code deleted successfully']);
    }

    public function getMyReferralUsage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $sql = <<<'SQL'
SELECT 
    ru.*,
    u1.email as referrer_email,
    u2.email as referred_email,
    o.order_number
FROM referral_usage ru
LEFT JOIN users u1 ON ru.referrer_user_id = u1.id
LEFT JOIN users u2 ON ru.referred_user_id = u2.id
LEFT JOIN orders o ON ru.order_id = o.id
WHERE ru.referrer_user_id = :user_id
ORDER BY ru.used_at DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->success($response, $usage);
    }

    public function getUsageById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $stmt = $this->db->prepare('SELECT referral_code FROM user_referrals WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $referralCode = $stmt->fetchColumn();

        if (!$referralCode) {
            return $this->error($response, 'Referral code not found', 404);
        }

        $sql = <<<'SQL'
SELECT 
    ru.*,
    u1.email as referrer_email,
    u2.email as referred_email,
    o.order_number
FROM referral_usage ru
LEFT JOIN users u1 ON ru.referrer_user_id = u1.id
LEFT JOIN users u2 ON ru.referred_user_id = u2.id
LEFT JOIN orders o ON ru.order_id = o.id
WHERE ru.referral_code = :code
ORDER BY ru.used_at DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $referralCode]);
        $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($response, $usage);
    }

    public function addUsage(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['referral_id'])) {
            return $this->error($response, 'referral_id is required', 400);
        }

        // Allow user_id as alias for referred_user_id
        $referredUserId = $body['referred_user_id'] ?? $body['user_id'] ?? null;

        if (empty($referredUserId)) {
            return $this->error($response, 'referred_user_id is required', 400);
        }

        $referral = UserReferral::find((int)$body['referral_id']);
        if (!$referral) {
            return $this->error($response, 'Referral not found', 404);
        }

        $orderId = isset($body['order_id']) && is_numeric($body['order_id']) ? (int)$body['order_id'] : null;
        $referral->recordUsage((int)$referredUserId, $orderId);

        return $this->success($response, ['message' => 'Referral usage recorded successfully']);
    }

    public function redeemPoints(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();
        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        if (!isset($body['points']) || $body['points'] <= 0) {
            return $this->error($response, 'Valid points amount is required', 400);
        }

        $pointsToRedeem = (int) $body['points'];
        try {
            $referral = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE user_id = :user_id', [':user_id' => $userId]);
            if (!$referral) {
                return $this->error($response, 'Referral account not found', 404);
            }

            $referral->redeemPoints($pointsToRedeem);
            return $this->success($response, [
                'message' => 'Points redeemed successfully',
                'points_redeemed' => $pointsToRedeem,
                'new_balance' => $referral->points_balance,
            ]);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    public function manualRedemption(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['referral_id'])) {
            return $this->error($response, 'referral_id is required', 400);
        }

        if (empty($body['amount'])) {
            return $this->error($response, 'Amount is required', 400);
        }

        $referral = UserReferral::find((int)$body['referral_id']);
        if (!$referral) {
            return $this->error($response, 'Referral not found', 404);
        }

        try {
            $referral->redeemPoints((int)$body['amount']);
            return $this->success($response, [
                'message' => 'Points redeemed successfully',
                'points_redeemed' => (int)$body['amount'],
                'new_balance' => $referral->points_balance,
            ]);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    public function getReferralLog(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $stmt = $this->db->prepare('SELECT user_id FROM user_referrals WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            return $this->error($response, 'Referral not found', 404);
        }

        $sql = <<<'SQL'
SELECT * FROM (
    SELECT 
        'earned' as type,
        points_awarded as points,
        used_at as date,
        'Referral used by ' || COALESCE(u.email, 'Unknown') as description,
        o.order_number as reference
    FROM referral_usage ru
    LEFT JOIN users u ON ru.referred_user_id = u.id
    LEFT JOIN orders o ON ru.order_id = o.id
    WHERE ru.referrer_user_id = :user_id

    UNION ALL

    SELECT 
        'redeemed' as type,
        -points_redeemed as points,
        redeemed_at as date,
        COALESCE(notes, 'Points redemption') as description,
        NULL as reference
    FROM referral_redemptions
    WHERE user_referral_id = :referral_id
) ORDER BY date DESC
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':referral_id' => $id]);
        $log = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($response, $log);
    }

    public function validateReferralCode(string $code, int $referredUserId): bool
    {
        try {
            $referral = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE referral_code = :code', [':code' => $code]);
            if (!$referral) {
                return false;
            }

            $referral->validate($referredUserId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function createReferralRecord(int $userId)
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $code = $this->generateReferralCode();
            try {
                $stmt = $this->db->prepare("INSERT INTO user_referrals (user_id, referral_code, discount_type, discount_amount, status, created_at, updated_at) VALUES (:user_id, :code, 'percentage', 10.0, 'active', datetime('now'), datetime('now'))");
                $stmt->execute([':user_id' => $userId, ':code' => $code]);

                $stmt = $this->db->prepare('SELECT * FROM user_referrals WHERE user_id = :user_id');
                $stmt->execute([':user_id' => $userId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // If collision on referral_code (unique constraint), retry
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
            }
        }
    }

    private function generateReferralCode(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[random_int(0, $max)];
        }
        return $code;
    }

    private function ensureSchema(): void
    {
        $stmt = $this->db->query("PRAGMA table_info(user_referrals)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        if (!in_array('discount_type', $columnNames)) {
            $this->db->exec("ALTER TABLE user_referrals ADD COLUMN discount_type TEXT DEFAULT 'percentage'");
        }
        if (!in_array('discount_amount', $columnNames)) {
            $this->db->exec("ALTER TABLE user_referrals ADD COLUMN discount_amount REAL DEFAULT 10.0");
        }
        if (!in_array('status', $columnNames)) {
            $this->db->exec("ALTER TABLE user_referrals ADD COLUMN status TEXT DEFAULT 'active'");
        }

        // Ensure referral_redemptions table exists
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='referral_redemptions'");
        if (!$stmt->fetch()) {
            $sql = <<<'SQL'
CREATE TABLE referral_redemptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_referral_id INTEGER NOT NULL,
    points_redeemed INTEGER NOT NULL,
    notes TEXT,
    redeemed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_referral_id) REFERENCES user_referrals(id) ON DELETE CASCADE
);
CREATE INDEX idx_referral_redemptions_referral ON referral_redemptions(user_referral_id);
SQL;
            $this->db->exec($sql);
        }

        // Ensure columns exist in referral_redemptions
        $stmt = $this->db->query("PRAGMA table_info(referral_redemptions)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        if (!in_array('notes', $columnNames)) {
            $this->db->exec("ALTER TABLE referral_redemptions ADD COLUMN notes TEXT");
        }
    }
}
