<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakeUserEmailNullable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->changeColumn('email', 'string', ['null' => true])
              ->save();
    }
}