<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUpdatedAtToCouponsAndInventory extends AbstractMigration
{
    public function change(): void
    {
        // Add updated_at to coupons table if it doesn't exist
        $coupons = $this->table('coupons');
        if (!$coupons->hasColumn('updated_at')) {
            $coupons->addColumn('updated_at', 'datetime', ['null' => true])
                ->update();
        }

        // Add updated_at to inventory table if it doesn't exist
        $inventory = $this->table('inventory');
        if (!$inventory->hasColumn('updated_at')) {
            $inventory->addColumn('updated_at', 'datetime', ['null' => true])
                ->update();
        }
    }
}