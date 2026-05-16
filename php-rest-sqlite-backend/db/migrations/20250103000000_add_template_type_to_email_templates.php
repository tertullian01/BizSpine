<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTemplateTypeToEmailTemplates extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('email_templates');
        if (!$table->hasColumn('template_type')) {
            $table->addColumn('template_type', 'string', ['null' => true])
                  ->save();
        }
    }

    public function down(): void
    {
        $table = $this->table('email_templates');
        if ($table->hasColumn('template_type')) {
            $table->removeColumn('template_type')->save();
        }
    }
}