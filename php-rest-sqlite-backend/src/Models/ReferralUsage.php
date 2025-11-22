<?php

namespace App\Models;

class ReferralUsage extends BaseModel
{
    protected static string $tableName = 'referral_usage';
// Additional properties for joined data
    public ?string $referrer_email;
    public ?string $referred_email;
    public ?string $order_number;
}
