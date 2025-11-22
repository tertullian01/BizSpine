<?php
namespace App\Models;

class Return
{
    public ?int $id;
    public int $order_id;
    public int $user_id;
    public string $return_number;
    public string $status; // requested, approved, rejected, completed
    public ?string $reason;
    public float $refund_amount;
    public ?string $refund_method;
    public ?string $refund_date;
    public ?string $notes;
    public ?string $created_at;
    public ?string $updated_at;
    
    // Additional properties for joined data
    public ?string $order_number;
    public ?string $user_email;
    public ?array $items; // Return items
}