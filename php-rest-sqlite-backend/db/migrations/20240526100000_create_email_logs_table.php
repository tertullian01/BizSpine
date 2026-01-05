<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEmailLogsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('email_logs');
        $table->addColumn('recipient', 'text', ['null' => false])
              ->addColumn('subject', 'text', ['null' => false])
              ->addColumn('body', 'text', ['null' => true])
              ->addColumn('status', 'string', ['null' => false])
              ->addColumn('error_message', 'text', ['null' => true])
              ->addColumn('sent_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['status'])
              ->addIndex(['recipient'])
              ->create();
    }
}