<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Install/setup wizard created coupons with min_purchase and expires_at;
 * CouponController expects min_purchase_amount, valid_from, valid_until, description.
 */
final class AlignCouponsTableColumns extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('coupons')) {
            return;
        }

        $table = $this->table('coupons');

        if (!$table->hasColumn('min_purchase_amount')) {
            $table->addColumn('min_purchase_amount', 'float', [
                'null' => true,
                'default' => 0,
            ])->update();
        }

        if ($table->hasColumn('min_purchase')) {
            $this->execute(
                'UPDATE coupons SET min_purchase_amount = min_purchase '
                . 'WHERE min_purchase_amount IS NULL OR min_purchase_amount = 0'
            );
        }

        $table = $this->table('coupons');

        if (!$table->hasColumn('valid_from')) {
            $table->addColumn('valid_from', 'datetime', ['null' => true])->update();
        }

        if (!$table->hasColumn('valid_until')) {
            $table->addColumn('valid_until', 'datetime', ['null' => true])->update();
        }

        if (!$table->hasColumn('description')) {
            $table->addColumn('description', 'text', ['null' => true])->update();
        }

        $table = $this->table('coupons');
        if ($table->hasColumn('expires_at') && $table->hasColumn('valid_until')) {
            $this->execute(
                'UPDATE coupons SET valid_until = expires_at WHERE valid_until IS NULL AND expires_at IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        // Non-destructive alignment migration; no down.
    }
}
