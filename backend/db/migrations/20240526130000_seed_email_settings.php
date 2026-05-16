<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedEmailSettings extends AbstractMigration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'smtp_host',
                'value' => 'smtp.example.com',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'SMTP Server Host',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'smtp_port',
                'value' => '587',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'SMTP Server Port',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'smtp_username',
                'value' => '',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'SMTP Username',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'smtp_password',
                'value' => '',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'SMTP Password',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'SMTP Encryption (tls/ssl)',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'from_email',
                'value' => 'noreply@example.com',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'From Email Address',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'from_name',
                'value' => 'My Store',
                'type' => 'string',
                'group_name' => 'email',
                'description' => 'From Name',
                'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('settings');
        
        foreach ($settings as $setting) {
            $exists = $this->fetchRow(sprintf("SELECT * FROM settings WHERE key = '%s'", $setting['key']));
            if (!$exists) {
                $table->insert([$setting])->saveData();
            }
        }
    }

    public function down(): void
    {
        // No action needed
    }
}