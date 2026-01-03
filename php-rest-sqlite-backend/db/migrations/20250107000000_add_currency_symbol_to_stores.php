<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCurrencySymbolToStores extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('stores');
        if (!$table->hasColumn('currency_symbol')) {
            $table->addColumn('currency_symbol', 'string', ['default' => '$', 'null' => true, 'after' => 'email']);
            $table->update();
        }
    }
}