<?php
namespace App\Models;

class OrderReturn extends BaseModel
{
    protected static string $tableName = 'returns';
    
    // Additional properties for joined data
    public ?string $order_number;
    public ?string $user_email;
    public ?array $items; // Return items

    public function getItems(): array
    {
        if ($this->id === null) {
            return [];
        }
        return ReturnItem::fetchAll('SELECT ri.*, p.name as product_name, s.name as store_name FROM return_items ri LEFT JOIN products p ON ri.product_id = p.id LEFT JOIN stores s ON ri.store_id = s.id WHERE ri.return_id = :return_id', [':return_id' => $this->id]);
    }

    public static function createReturn(array $body, int $userId): OrderReturn
    {
        if (empty($body['order_id']) || empty($body['items']) || !is_array($body['items'])) {
            throw new \Exception('order_id and items are required');
        }

        self::$db->beginTransaction();

        try {
            // Verify order exists and belongs to user
            $order = Order::fetchOne('SELECT * FROM orders WHERE id = :id AND user_id = :user_id', [':id' => $body['order_id'], ':user_id' => $userId]);
            
            if (!$order) {
                throw new \Exception('Order not found or does not belong to user');
            }
            
            if (!in_array($order->fulfillment_status, ['delivered', 'shipped'])) {
                throw new \Exception('Can only return delivered or shipped orders');
            }
            
            // Generate return number
            $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Calculate total refund
            $totalRefund = 0;
            $validatedItems = [];
            
            foreach ($body['items'] as $item) {
                if (empty($item['order_item_id']) || empty($item['quantity'])) {
                    throw new \Exception('Each item must have order_item_id and quantity');
                }
                
                // Get order item details
                $orderItem = OrderItem::fetchOne('SELECT * FROM order_items WHERE id = :id AND order_id = :order_id', [':id' => $item['order_item_id'], ':order_id' => $body['order_id']]);
                
                if (!$orderItem) {
                    throw new \Exception("Order item {$item['order_item_id']} not found");
                }
                
                $returnQty = (int)$item['quantity'];
                if ($returnQty > $orderItem->quantity) {
                    throw new \Exception("Cannot return more than ordered quantity");
                }
                
                $refundAmount = ($orderItem->unit_price * $returnQty);
                $totalRefund += $refundAmount;
                
                $validatedItems[] = [
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $orderItem->product_id,
                    'store_id' => $orderItem->store_id,
                    'quantity' => $returnQty,
                    'refund_amount' => $refundAmount,
                    'reason' => $item['reason'] ?? null,
                ];
            }
            
            // Create return
            $return = new OrderReturn([
                'order_id' => $body['order_id'],
                'user_id' => $userId,
                'return_number' => $returnNumber,
                'status' => 'requested',
                'reason' => $body['reason'] ?? null,
                'refund_amount' => $totalRefund,
                'notes' => $body['notes'] ?? null,
            ]);
            $return->save();
            
            // Create return items
            foreach ($validatedItems as $item) {
                $returnItem = new ReturnItem([
                    'return_id' => $return->id,
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $item['product_id'],
                    'store_id' => $item['store_id'],
                    'quantity' => $item['quantity'],
                    'refund_amount' => $item['refund_amount'],
                    'reason' => $item['reason'],
                ]);
                $returnItem->save();
            }
            
            self::$db->commit();
            
            return $return;
            
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    public function approve(): void
    {
        if ($this->status !== 'requested') {
            throw new \Exception('Can only approve requested returns');
        }

        self::$db->beginTransaction();

        try {
            // Get return items
            $items = $this->getItems();
            
            // Restore inventory
            foreach ($items as $item) {
                $inventory = Inventory::fetchOne('SELECT * FROM inventory WHERE product_id = :product_id AND store_id = :store_id', [':product_id' => $item->product_id, ':store_id' => $item->store_id]);
                if ($inventory) {
                    $inventory->adjustQuantity($item->quantity);
                }
            }
            
            // Update return status
            $this->status = 'approved';
            $this->save();
            
            self::$db->commit();
            
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    public function processRefund(array $body): void
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Return must be approved before processing refund');
        }

        self::$db->beginTransaction();

        try {
            // Update return with refund info
            $this->status = 'completed';
            $this->refund_method = $body['refund_method'] ?? 'Original Payment Method';
            $this->refund_date = date('Y-m-d H:i:s');
            $this->save();
            
            // Create expense record for refund
            $expense = new Expense([
                'order_id' => $this->order_id,
                'category' => 'Refund',
                'amount' => $this->refund_amount,
                'expense_date' => date('Y-m-d H:i:s'),
                'description' => "Refund for return {$this->return_number}",
                'notes' => $body['notes'] ?? null,
            ]);
            $expense->save();
            
            self::$db->commit();
            
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }
}