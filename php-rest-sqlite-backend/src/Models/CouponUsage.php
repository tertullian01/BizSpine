<?php

namespace App\Models;

class CouponUsage extends BaseModel
{
    protected static string $tableName = 'coupon_usage';

    public ?int $coupon_id = null;
    public ?int $user_id = null;
    public ?int $order_id = null;
    public ?float $discount_amount = null;
    public ?string $used_at = null;
}