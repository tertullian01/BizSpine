<?php

namespace App\Models;

#[\AllowDynamicProperties]
class UserReferral extends BaseModel
{
    protected static string $tableName = 'user_referrals';

    public int $user_id;
    public string $referral_code;
    public int $times_used = 0;
    public int $points_balance = 0;
    public int $points_earned = 0;
    public int $points_redeemed = 0;
    public ?string $created_at;
    public ?string $updated_at = null;
    public ?string $discount_type = null;
    public ?float $discount_amount = null;
    public ?string $status = null;

    // Additional properties for joined data
    public ?string $user_email;
    public function validate(int $referredUserId): void
    {
        if ($this->user_id === $referredUserId) {
            throw new \Exception('You cannot refer yourself');
        }

        $orderCount = self::$db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id AND fulfillment_status != 'cancelled'");
        $orderCount->execute([':user_id' => $referredUserId]);
        if ((int) $orderCount->fetchColumn() > 0) {
            throw new \Exception('Referral code is only valid for the first order');
        }
    }

    public static function createForUser(int $userId): UserReferral
    {
        $referralCode = self::generateReferralCode();
        $referral = new UserReferral([
            'user_id' => $userId,
            'referral_code' => $referralCode,
            'times_used' => 0,
            'points_balance' => 0,
            'points_earned' => 0,
            'points_redeemed' => 0,
            'discount_type' => 'percentage',
            'discount_amount' => 10.0,
            'status' => 'active',
        ]);
        $referral->save();
        return $referral;
    }

    private static function generateReferralCode(): string
    {
        do {
            $code = 'REF-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $stmt = self::$db->prepare('SELECT id FROM user_referrals WHERE referral_code = :code');
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());
        return $code;
    }

    public function redeemPoints(int $pointsToRedeem, ?string $notes = null): void
    {
        if ($this->points_balance < $pointsToRedeem) {
            throw new \Exception('Insufficient points balance');
        }

        $this->points_redeemed += $pointsToRedeem;
        $this->points_balance -= $pointsToRedeem;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        // Log redemption
        $redemption = new ReferralRedemption([
            'user_referral_id' => $this->id,
            'points_redeemed' => $pointsToRedeem,
            'notes' => $notes,
            'redeemed_at' => date('Y-m-d H:i:s'),
        ]);
        $redemption->save();
    }

    public function recordUsage(int $referredUserId, ?int $orderId = null): void
    {
        $usage = new ReferralUsage([
            'referrer_user_id' => $this->user_id,
            'referred_user_id' => $referredUserId,
            'referral_code' => $this->referral_code,
            'order_id' => $orderId,
            'points_awarded' => 100,
            'used_at' => date('Y-m-d H:i:s'),
        ]);
        $usage->save();

        $this->times_used++;
        $this->points_earned += 100;
        $this->points_balance += 100;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
    }
}
