<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateReferralRedemptionsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('referral_redemptions');
        $table->addColumn('user_referral_id', 'integer', ['null' => false])
              ->addColumn('points_redeemed', 'integer', ['null' => false])
              ->addColumn('notes', 'text', ['null' => true])
              ->addColumn('redeemed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addForeignKey('user_referral_id', 'user_referrals', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
              ->addIndex(['user_referral_id'])
              ->create();
    }
}