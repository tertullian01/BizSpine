<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddShippingFieldsToOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        
        if (!$table->hasColumn('shipping_method')) {
            $table->addColumn('shipping_method', 'string', ['null' => true, 'after' => 'tracking_number']);
        }
        
        if (!$table->hasColumn('shipping_carrier')) {
            $table->addColumn('shipping_carrier', 'string', ['null' => true, 'after' => 'shipping_method']);
        }
        
        $table->update();
    }
}