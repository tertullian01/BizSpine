<?php

namespace App\Models;

class ReferralRedemption extends BaseModel
{
    protected static string $tableName = 'referral_redemptions';

    public ?int $user_referral_id = null;
    public ?int $order_id = null;
    public ?int $points_redeemed = null;
    public ?string $notes = null;
    public ?string $redeemed_at = null;
}
