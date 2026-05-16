<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTransactionIdToIncome extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('income');
        
        if (!$table->hasColumn('transaction_id')) {
            $table->addColumn('transaction_id', 'string', ['null' => true])
                  ->update();
        }
    }
}