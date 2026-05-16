<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnableBlobInSettings extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('settings');
        
        // Update the 'value' column to 'binary' type (BLOB in SQLite).
        // This allows storing raw image data while preserving existing text settings.
        $table->changeColumn('value', 'binary', ['null' => true])
              ->save();
    }

    public function down(): void
    {
        $table = $this->table('settings');
        $table->changeColumn('value', 'text', ['null' => true])
              ->save();
    }
}