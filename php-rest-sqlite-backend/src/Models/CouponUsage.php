<?php

namespace App\Models;

class CouponUsage extends BaseModel
{
    protected static string $tableName = 'coupon_usage';
// Additional properties for joined data
    public ?string $coupon_code;
    public ?string $user_email;
    public ?string $order_number;
}
