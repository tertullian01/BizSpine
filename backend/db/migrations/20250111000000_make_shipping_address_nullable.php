<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakeShippingAddressNullable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        $table->changeColumn('shipping_address', 'string', ['null' => true])
              ->save();
    }
}