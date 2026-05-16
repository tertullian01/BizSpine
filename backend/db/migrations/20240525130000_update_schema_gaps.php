<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateSchemaGaps extends AbstractMigration
{
    public function change(): void
    {
        // Income table
        $table = $this->table('income');
        if ($table->exists() && !$table->hasColumn('category')) {
            $table->addColumn('category', 'text', ['null' => true])->update();
        }

        // Products table
        $table = $this->table('products');
        if ($table->exists()) {
            if (!$table->hasColumn('image_url')) {
                $table->addColumn('image_url', 'text', ['null' => true]);
            }
            if (!$table->hasColumn('state')) {
                $table->addColumn('state', 'text', ['default' => 'For Sale', 'null' => true]);
            }
            $table->update();
        }

        // Product Reviews table
        $table = $this->table('product_reviews');
        if ($table->exists()) {
            if (!$table->hasColumn('verified')) {
                $table->addColumn('verified', 'integer', ['default' => 0, 'null' => true]);
            }
            if (!$table->hasColumn('published')) {
                $table->addColumn('published', 'integer', ['default' => 0, 'null' => true]);
            }
            if (!$table->hasColumn('updated_at')) {
                $table->addColumn('updated_at', 'datetime', ['null' => true]);
            }
            if (!$table->hasColumn('order_id')) {
                $table->addColumn('order_id', 'integer', ['null' => true]);
            }
            $table->update();
        }

        // Testimonials table
        $table = $this->table('testimonials');
        if ($table->exists()) {
            if (!$table->hasColumn('customer_email')) {
                $table->addColumn('customer_email', 'text', ['null' => true]);
            }
            if (!$table->hasColumn('published')) {
                $table->addColumn('published', 'integer', ['default' => 0, 'null' => true]);
            }
            if (!$table->hasColumn('updated_at')) {
                $table->addColumn('updated_at', 'datetime', ['null' => true]);
            }
            if (!$table->hasColumn('age_range')) {
                $table->addColumn('age_range', 'text', ['null' => true]);
            }
            if (!$table->hasColumn('image_url')) {
                $table->addColumn('image_url', 'text', ['null' => true]);
            }
            $table->update();
        }

        // Orders table
        $table = $this->table('orders');
        if ($table->exists() && !$table->hasColumn('store_id')) {
            $table->addColumn('store_id', 'integer', ['null' => true])->update();
        }
    }
}