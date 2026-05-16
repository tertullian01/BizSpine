<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakePasswordHashNullable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->changeColumn('password_hash', 'string', ['null' => true])
              ->save();
    }
}