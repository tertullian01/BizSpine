<?php

namespace App\Controllers;

use App\Models\UserReferral;
use App\Models\ReferralUsage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReferralController extends ApiController
{
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
        $referrals = UserReferral::fetchAll($sql);
        return $this->success($response, $referrals);
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
        $referral = UserReferral::fetchOne($sql, [':user_id' => $userId]);
        if (!$referral) {
            // Create referral code if doesn't exist
            $referral = UserReferral::createForUser($userId);
        }

        return $this->success($response, $referral);
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
        $usage = ReferralUsage::fetchAll($sql, [':user_id' => $userId]);
        return $this->success($response, $usage);
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
}
