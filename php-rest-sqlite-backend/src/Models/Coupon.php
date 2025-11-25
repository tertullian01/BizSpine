<?php

namespace App\Models;

class Coupon extends BaseModel
{
    protected static string $tableName = 'coupons';

    public ?int $id = null;
    public ?string $code = null;
    public ?string $discount_type = null;
    public ?float $discount_value = null;
    public ?float $min_purchase_amount = null;
    public ?int $max_uses = null;
    public ?int $times_used = null;
    public ?string $valid_from = null;
    public ?string $valid_until = null;
    public ?int $is_active = null;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public function validate(float $subtotal, int $userId): array
    {
        if (!$this->id) {
            return ['valid' => false, 'error' => 'Coupon not found'];
        }

        if (!$this->is_active) {
            return ['valid' => false, 'error' => 'Coupon is not active'];
        }

        if ($this->valid_from && strtotime($this->valid_from) > time()) {
            return ['valid' => false, 'error' => 'Coupon is not yet valid'];
        }

        if ($this->valid_until && strtotime($this->valid_until) < time()) {
            return ['valid' => false, 'error' => 'Coupon has expired'];
        }

        if ($this->max_uses && $this->times_used >= $this->max_uses) {
            return ['valid' => false, 'error' => 'Coupon has reached its usage limit'];
        }

        if ($subtotal < $this->min_purchase_amount) {
            return ['valid' => false, 'error' => "Minimum purchase amount of {$this->min_purchase_amount} not met"];
        }

        // Check if user has already used this coupon
        $usage = CouponUsage::fetchOne('SELECT * FROM coupon_usage WHERE coupon_id = :coupon_id AND user_id = :user_id', [':coupon_id' => $this->id, ':user_id' => $userId]);
        if ($usage) {
            return ['valid' => false, 'error' => 'You have already used this coupon'];
        }

        $discountAmount = 0;
        if ($this->discount_type === 'percentage') {
            $discountAmount = $subtotal * ($this->discount_value / 100);
        } else {
            $discountAmount = $this->discount_value;
        }

        return [
            'valid' => true,
            'discount_amount' => $discountAmount,
            'coupon_id' => $this->id,
        ];
    }

    public function recordUsage(int $userId, int $orderId, float $discountAmount): void
    {
        $usage = new CouponUsage([
            'coupon_id' => $this->id,
            'user_id' => $userId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
            'used_at' => date('Y-m-d H:i:s'),
        ]);
        $usage->save();
        $this->times_used++;
        $this->save();
    }
}
