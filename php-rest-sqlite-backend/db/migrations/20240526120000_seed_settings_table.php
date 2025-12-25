<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedSettingsTable extends AbstractMigration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'store_name',
                'value' => 'My Small Business',
                'type' => 'string',
                'group_name' => 'general',
                'description' => 'The name of your store',
                'is_public' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'store_email',
                'value' => 'admin@example.com',
                'type' => 'string',
                'group_name' => 'general',
                'description' => 'Contact email for the store',
                'is_public' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'store_phone',
                'value' => '',
                'type' => 'string',
                'group_name' => 'general',
                'description' => 'Store phone number',
                'is_public' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'key' => 'store_address',
                'value' => '',
                'type' => 'string',
                'group_name' => 'general',
                'description' => 'Store physical address',
                'is_public' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        ];

        $table = $this->table('settings');
        
        foreach ($settings as $setting) {
            // Check if key already exists to prevent errors if re-running
            $exists = $this->fetchRow(sprintf("SELECT * FROM settings WHERE key = '%s'", $setting['key']));
            if (!$exists) {
                $table->insert([$setting])->saveData();
            }
        }
    }

    public function down(): void
    {
        // No action needed on rollback to preserve data
    }
}