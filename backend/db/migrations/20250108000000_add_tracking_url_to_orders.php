<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTrackingUrlToOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        
        if (!$table->hasColumn('tracking_url')) {
            $table->addColumn('tracking_url', 'string', ['null' => true]);
            $table->update();
        }
    }
}