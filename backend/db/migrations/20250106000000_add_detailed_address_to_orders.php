<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDetailedAddressToOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        
        $columns = ['city', 'state', 'postal_code', 'country'];
        foreach ($columns as $column) {
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, 'string', ['null' => true]);
            }
        }
        
        $table->update();
    }
}