<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AllowGuestCheckout extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        if (!$table->exists() || $table->hasColumn('customer_email')) {
            return;
        }

        $table->changeColumn('user_id', 'integer', ['null' => true])
              ->addColumn('customer_email', 'string', ['null' => true])
              ->addColumn('customer_name', 'string', ['null' => true])
              ->update();
    }
}