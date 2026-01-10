<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddContactUsEmailTemplate extends AbstractMigration
{
    public function up(): void
    {
        $exists = $this->fetchRow("SELECT id FROM email_templates WHERE name = 'contact_us'");
        
        if (!$exists) {
            $this->table('email_templates')->insert([
                [
                    'name' => 'contact_us',
                    'store_id' => null,
                    'template_type' => 'notification',
                    'subject' => 'New Contact Inquiry: {{subject}}',
                    'body' => "<h2>New Contact Message</h2>\n<p><strong>Name:</strong> {{name}}</p>\n<p><strong>Email:</strong> {{email}}</p>\n<p><strong>Date:</strong> {{timestamp}}</p>\n<hr>\n<h3>Message:</h3>\n<p>{{message}}</p>",
                    'placeholders' => json_encode(['name', 'email', 'subject', 'message', 'timestamp']),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            ])->save();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM email_templates WHERE name = 'contact_us'");
    }
}