<?php

namespace App\Models;

class ReferralUsage extends BaseModel
{
    protected static string $tableName = 'referral_usage';

    public ?int $referrer_user_id = null;
    public ?int $referred_user_id = null;
    public ?string $referral_code = null;
    public ?int $order_id = null;
    public ?int $points_awarded = null;
    public ?string $used_at = null;

// Additional properties for joined data
    public ?string $referrer_email;
    public ?string $referred_email;
    public ?string $order_number;
}
