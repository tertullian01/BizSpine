<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStoreIdToEmailTemplates extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('email_templates');
        
        if (!$table->hasColumn('store_id')) {
            $table->addColumn('store_id', 'integer', ['null' => true, 'after' => 'id'])
                  ->addForeignKey('store_id', 'stores', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                  ->save();
        }

        if ($table->hasIndex(['name'])) {
            $table->removeIndex(['name'])->save();
        }

        $table->addIndex(['name', 'store_id'], ['unique' => true, 'name' => 'idx_email_templates_name_store'])->save();
    }

    public function down(): void
    {
        $table = $this->table('email_templates');
        if ($table->hasIndex(['name', 'store_id'])) {
            $table->removeIndex(['name', 'store_id'])->save();
        }
        if ($table->hasColumn('store_id')) {
            $table->removeColumn('store_id')->save();
        }
        $table->addIndex(['name'], ['unique' => true])->save();
    }
}