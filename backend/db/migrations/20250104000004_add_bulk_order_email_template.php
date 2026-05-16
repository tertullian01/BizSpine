<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBulkOrderEmailTemplate extends AbstractMigration
{
    public function up(): void
    {
        $exists = $this->fetchRow("SELECT id FROM email_templates WHERE name = 'bulk_order_inquiry'");
        
        if (!$exists) {
            $this->table('email_templates')->insert([
                [
                    'name' => 'bulk_order_inquiry',
                    'subject' => 'New Bulk Order Inquiry',
                    'body' => "<h2>New Bulk Order Inquiry</h2>\n<p><strong>Name:</strong> {{name}}</p>\n<p><strong>Email:</strong> {{email}}</p>\n<p><strong>Phone:</strong> {{phone}}</p>\n<p><strong>Company:</strong> {{company}}</p>\n<p><strong>Shipping Address:</strong> {{shipping_address}}</p>\n<hr>\n<p><strong>Date:</strong> {{timestamp}}</p>\n<hr>\n<h3>Message/Details:</h3>\n<p>{{message}}</p>",
                    'placeholders' => json_encode(['name', 'email', 'phone', 'company', 'shipping_address', 'message', 'timestamp']),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            ])->save();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM email_templates WHERE name = 'bulk_order_inquiry'");
    }
}