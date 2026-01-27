<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddReplyToEmailLogs extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('email_logs');
        
        if ($table->exists() && !$table->hasColumn('reply_to')) {
            $table->addColumn('reply_to', 'string', ['null' => true, 'after' => 'subject'])
                  ->update();
        }
    }
}