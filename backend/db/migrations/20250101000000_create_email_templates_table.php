<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEmailTemplatesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('email_templates');
        $table->addColumn('name', 'string', ['limit' => 100])
              ->addColumn('subject', 'string', ['limit' => 255])
              ->addColumn('body', 'text')
              ->addColumn('placeholders', 'text', ['null' => true]) // JSON string description
              ->addColumn('created_at', 'datetime')
              ->addColumn('updated_at', 'datetime')
              ->addIndex(['name'], ['unique' => true])
              ->create();

        // Seed default templates
        $defaultTemplates = [
            [
                'name' => 'order_confirmation',
                'subject' => 'Order Confirmation - {{order_number}}',
                'body' => '<h2>Thank you for your order!</h2><p>Order Number: <strong>{{order_number}}</strong></p><h3>Items:</h3>{{items_list}}<p><strong>Total: {{total}}</strong></p><p>Shipping Address: {{shipping_address}}</p>',
                'placeholders' => json_encode(['order_number', 'items_list', 'total', 'shipping_address']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'password_reset',
                'subject' => 'Password Reset Request',
                'body' => '<p>Click the following link to reset your password: <a href="{{reset_link}}">{{reset_link}}</a></p>',
                'placeholders' => json_encode(['reset_link']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        ];
        $this->table('email_templates')->insert($defaultTemplates)->saveData();
    }

    public function down(): void
    {
        $this->table('email_templates')->drop()->save();
    }
}