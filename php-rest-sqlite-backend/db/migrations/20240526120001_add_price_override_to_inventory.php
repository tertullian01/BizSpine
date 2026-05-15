<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPriceOverrideToInventory extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('inventory');
        
        if (!$table->hasColumn('price_override')) {
            $table->addColumn('price_override', 'float', [
                'null' => true,
                'default' => null,
                'after' => 'max_quantity'
            ])->update();
        }
    }
}