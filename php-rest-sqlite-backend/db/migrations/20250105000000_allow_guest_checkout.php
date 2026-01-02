<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AllowGuestCheckout extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        
        // Make user_id nullable and add customer fields
        $table->changeColumn('user_id', 'integer', ['null' => true])
              ->addColumn('customer_email', 'string', ['null' => true, 'after' => 'user_id'])
              ->addColumn('customer_name', 'string', ['null' => true, 'after' => 'customer_email'])
              ->save();
              
        // Note: Phinx handles SQLite table recreation for column changes automatically
        // but foreign keys might need attention if not handled by the wrapper.
        // In this simple case, it should work.
    }
}