<?php

declare(strict_types=1);

use Databoss\ConnectionInterface;
use Kram\MigrationInterface;

class CreateLicenseResetsTable implements MigrationInterface
{
    public function up(ConnectionInterface $connection): void
    {
        $connection->create('license_resets', [
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
                'primary' => true,
            ],
            'license_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'oauth_user_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'reset_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $connection->index('license_resets', ['license_id'], 'idx_license_id');
        $connection->index('license_resets', ['oauth_user_id'], 'idx_oauth_user_id');

        $connection->foreign('license_resets', 'license_id', ['licenses', 'id'], 'fk_reset_license');
        $connection->foreign('license_resets', 'oauth_user_id', ['oauth_users', 'id'], 'fk_reset_oauth_user');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->drop('license_resets');
    }
}
