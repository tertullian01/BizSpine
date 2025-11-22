<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\UserReferral;
use App\Models\ReferralUsage;
use App\Models\TaxRate;

class Order extends BaseModel
{
    protected static string $tableName = 'orders';
// Additional properties for joined data
    public ?string $user_email;
    public ?array $items;
// Order items

    public function getItems(): array
    {
        if ($this->id === null) {
            return [];
        }
        return OrderItem::fetchAll('SELECT oi.*, p.name as product_name, s.name as store_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id LEFT JOIN stores s ON oi.store_id = s.id WHERE oi.order_id = :order_id', [':order_id' => $this->id]);
    }

    public static function createOrder(array $body, int $userId): Order
    {
        if (empty($body['shipping_address']) || empty($body['items']) || !is_array($body['items'])) {
            throw new \Exception('shipping_address and items are required');
        }

        if (count($body['items']) === 0) {
            throw new \Exception('Order must contain at least one item');
        }

        self::$db->beginTransaction();
        try {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $subtotal = 0;
            $validatedItems = [];
            foreach ($body['items'] as $item) {
                if (empty($item['product_id']) || empty($item['store_id']) || empty($item['quantity'])) {
                    throw new \Exception('Each item must have product_id, store_id, and quantity');
                }

                $productId = (int)$item['product_id'];
                $storeId = (int)$item['store_id'];
                $quantity = (int)$item['quantity'];
                if ($quantity <= 0) {
                    throw new \Exception('Quantity must be greater than 0');
                }

                $product = Product::find($productId);
                if (!$product) {
                    throw new \Exception("Product with ID $productId not found");
                }

                $unitPrice = (float)$product->cost;
                $itemSubtotal = $unitPrice * $quantity;
                $subtotal += $itemSubtotal;
                $inventory = Inventory::fetchOne('SELECT * FROM inventory WHERE product_id = :product_id AND store_id = :store_id', [':product_id' => $productId, ':store_id' => $storeId]);
                if (!$inventory) {
                    throw new \Exception("Product not available at selected store");
                }

                if ($inventory->quantity < $quantity) {
                    throw new \Exception("Insufficient inventory for product ID $productId (available: {$inventory->quantity}, requested: $quantity)");
                }

                $validatedItems[] = [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                    'inventory' => $inventory,
                ];
            }

            $hasCoupon = !empty($body['coupon_code']);
            $hasReferral = !empty($body['referral_code']);
            if ($hasCoupon && $hasReferral) {
                throw new \Exception('Cannot use both coupon code and referral code on the same order');
            }

            $discountAmount = 0;
            $couponCode = null;
            $couponResult = null;
            if ($hasCoupon) {
                $coupon = Coupon::fetchOne('SELECT * FROM coupons WHERE code = :code', [':code' => $body['coupon_code']]);
                if (!$coupon) {
                    throw new \Exception('Invalid coupon code');
                }
                $couponResult = $coupon->validate($subtotal, $userId);
                if (!$couponResult['valid']) {
                    throw new \Exception($couponResult['error']);
                }

                $discountAmount = $couponResult['discount_amount'];
                $couponCode = $body['coupon_code'];
            } elseif (isset($body['discount_amount'])) {
                $discountAmount = (float)$body['discount_amount'];
            }
            if ($hasReferral) {
                $referral = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE referral_code = :code', [':code' => $body['referral_code']]);
                if ($referral) {
                    $referral->validate($userId);
                } else {
                    throw new \Exception('Invalid referral code');
                }
            }

            $shippingCost = isset($body['shipping_cost']) ? (float)$body['shipping_cost'] : 0;
            $taxableAmount = $subtotal - $discountAmount + $shippingCost;
            $taxCalc = TaxRate::calculateTax($taxableAmount, $body['tax_region'] ?? null);
            $taxRate = $taxCalc['tax_rate'];
            $taxAmount = $taxCalc['tax_amount'];
            $total = $taxCalc['total_with_tax'];
            $order = new Order([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'shipping_address' => $body['shipping_address'],
                'phone_number' => $body['phone_number'] ?? null,
                'whatsapp_number' => $body['whatsapp_number'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'shipping_cost' => $shippingCost,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'notes' => $body['notes'] ?? null,
                'order_date' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $order->save();
            foreach ($validatedItems as $item) {
                $orderItem = new OrderItem([
                                'order_id' => $order->id,
                                'product_id' => $item['product_id'],
                                'store_id' => $item['store_id'],
                                'quantity' => $item['quantity'],
                                'unit_price' => $item['unit_price'],
                                'subtotal' => $item['subtotal'],
                                'created_at' => date('Y-m-d H:i:s'),
                ]);
                $orderItem->save();
                $item['inventory']->adjustQuantity(-$item['quantity']);
            }

            if ($hasCoupon && $couponResult && $couponResult['valid']) {
                $usage = new CouponUsage([
                    'coupon_id' => $couponResult['coupon_id'],
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'discount_amount' => $discountAmount,
                    'used_at' => date('Y-m-d H:i:s'),
                ]);
                $usage->save();
            }

            if ($hasReferral) {
                $referral = UserReferral::fetchOne('SELECT * FROM user_referrals WHERE referral_code = :code', [':code' => $body['referral_code']]);
                if ($referral) {
                    $usage = new ReferralUsage([
                        'referrer_user_id' => $referral->user_id,
                        'referred_user_id' => $userId,
                        'referral_code' => $referral->referral_code,
                        'order_id' => $order->id,
                        'points_awarded' => 10, // Or some other value
                        'used_at' => date('Y-m-d H:i:s'),
                    ]);
                    $usage->save();
                    $referral->times_used++;
                    $referral->points_balance += 10;
                    $referral->points_earned += 10;
                    $referral->save();
                }
            }

            self::$db->commit();
            return $order;
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    public function updateOrder(array $body): void
    {
        self::$db->beginTransaction();
        try {
            $createShippingExpense = false;
            if (isset($body['fulfillment_status'])) {
                $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($body['fulfillment_status'], $validStatuses)) {
                    throw new \Exception('Invalid fulfillment status');
                }
                $this->fulfillment_status = $body['fulfillment_status'];
                if ($this->fulfillment_status === 'shipped' && $this->shipping_date === null) {
                    $this->shipping_date = date('Y-m-d H:i:s');
                    if ($this->shipping_cost > 0) {
                        $createShippingExpense = true;
                    }
                }
            }

            if (isset($body['tracking_number'])) {
                $this->tracking_number = $body['tracking_number'];
                if ($this->fulfillment_status === 'shipped' && $this->shipping_cost > 0) {
                    $createShippingExpense = true;
                }
            }

            if (isset($body['shipping_cost'])) {
                $this->shipping_cost = (float)$body['shipping_cost'];
                $this->total = $this->subtotal - $this->discount_amount + $this->shipping_cost;
            }

            if (isset($body['notes'])) {
                $this->notes = $body['notes'];
            }

            $this->updated_at = date('Y-m-d H:i:s');
            $this->save();
            if ($createShippingExpense) {
                $expense = new Expense([
                'order_id' => $this->id,
                'vendor' => 'Shipping Provider',
                'category' => 'Shipping',
                'amount' => $this->shipping_cost,
                'expense_date' => date('Y-m-d H:i:s'),
                'description' => "Shipping cost for order {$this->order_number}",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $expense->save();
            }

            self::$db->commit();
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    public function addPayment(array $body): Income
    {
        if (!isset($body['amount']) || $body['amount'] <= 0) {
            throw new \Exception('Valid amount is required');
        }

        self::$db->beginTransaction();
        try {
            $income = new Income([
                'order_id' => $this->id,
                'amount' => (float)$body['amount'],
                'payment_method' => $body['payment_method'] ?? 'Unknown',
                'payment_date' => date('Y-m-d H:i:s'),
                'description' => "Payment for order {$this->order_number}",
                'notes' => $body['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $income->save();
            self::$db->commit();
            return $income;
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    public function cancel(): void
    {
        if ($this->fulfillment_status === 'cancelled') {
            throw new \Exception('Order is already cancelled');
        }

        if (in_array($this->fulfillment_status, ['shipped', 'delivered'])) {
            throw new \Exception('Cannot cancel order that has been shipped or delivered');
        }

        self::$db->beginTransaction();
        try {
            $items = $this->getItems();
            foreach ($items as $item) {
                $inventory = Inventory::fetchOne('SELECT * FROM inventory WHERE product_id = :product_id AND store_id = :store_id', [':product_id' => $item->product_id, ':store_id' => $item->store_id]);
                if ($inventory) {
                    $inventory->adjustQuantity($item->quantity);
                }
            }

            $this->fulfillment_status = 'cancelled';
            $this->updated_at = date('Y-m-d H:i:s');
            $this->save();
            self::$db->commit();
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }
}
