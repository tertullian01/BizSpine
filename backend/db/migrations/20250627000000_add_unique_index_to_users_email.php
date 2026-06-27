<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Restore email uniqueness on users. The nullable-email migration may have
 * recreated the table without the original UNIQUE column constraint.
 */
final class AddUniqueIndexToUsersEmail extends AbstractMigration
{
    private const INDEX_NAME = 'idx_users_email_unique';

    public function up(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        if ($this->usersEmailUniqueIndexExists()) {
            return;
        }

        // Blank emails are stored as NULL so guest accounts do not collide on ''.
        $this->execute(
            "UPDATE users SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''"
        );

        $duplicates = $this->fetchAll(
            "SELECT email, COUNT(*) AS cnt FROM users
             WHERE email IS NOT NULL
             GROUP BY email
             HAVING COUNT(*) > 1"
        );

        if ($duplicates !== []) {
            $details = array_map(
                static fn (array $row): string => ($row['email'] ?? '') . ' (' . ($row['cnt'] ?? '?') . ' rows)',
                $duplicates
            );
            throw new RuntimeException(
                'Cannot add unique email index: duplicate emails exist: '
                . implode(', ', $details)
                . '. Resolve duplicates before running this migration.'
            );
        }

        $this->execute(
            'CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX_NAME . ' ON users (email)'
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        $this->execute('DROP INDEX IF EXISTS ' . self::INDEX_NAME);
    }

    private function usersEmailUniqueIndexExists(): bool
    {
        $indexes = $this->fetchAll("PRAGMA index_list('users')");

        foreach ($indexes as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1) {
                continue;
            }

            $indexName = (string) $index['name'];
            $columns = $this->fetchAll("PRAGMA index_info('{$indexName}')");
            $columnNames = array_column($columns, 'name');

            if ($columnNames === ['email']) {
                return true;
            }
        }

        return false;
    }
}
