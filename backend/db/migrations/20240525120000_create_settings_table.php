<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('settings');
        if (!$table->exists()) {
            $table->addColumn('key', 'string', ['null' => false])
                  ->addColumn('value', 'text', ['null' => true])
                  ->addColumn('type', 'string', ['default' => 'string', 'null' => true])
                  ->addColumn('group_name', 'string', ['default' => 'general', 'null' => true])
                  ->addColumn('description', 'text', ['null' => true])
                  ->addColumn('is_public', 'integer', ['default' => 0, 'null' => true])
                  ->addIndex(['key'], ['unique' => true])
                  ->addTimestamps()
                  ->create();
        } else {
            // Ensure new columns exist if table already exists
            $columns = [
                'type' => ['string', ['default' => 'string', 'null' => true]],
                'group_name' => ['string', ['default' => 'general', 'null' => true]],
                'description' => ['text', ['null' => true]],
                'is_public' => ['integer', ['default' => 0, 'null' => true]]
            ];

            foreach ($columns as $name => $def) {
                if (!$table->hasColumn($name)) {
                    $table->addColumn($name, $def[0], $def[1]);
                }
            }
            $table->update();
        }
    }
}