<?php
namespace App\Models;

class UserReferral extends BaseModel
{
    protected static string $tableName = 'user_referrals';
    
    // Additional properties for joined data
    public ?string $user_email;

    public function validate(int $referredUserId): void
    {
        if ($this->user_id === $referredUserId) {
            throw new \Exception('You cannot refer yourself');
        }

        $orderCount = self::$db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
        $orderCount->execute([':user_id' => $referredUserId]);
        if ((int)$orderCount->fetchColumn() > 0) {
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

    public function redeemPoints(int $pointsToRedeem): void
    {
        if ($this->points_balance < $pointsToRedeem) {
            throw new \Exception('Insufficient points balance');
        }

        $this->points_redeemed += $pointsToRedeem;
        $this->points_balance -= $pointsToRedeem;
        $this->save();
    }
}