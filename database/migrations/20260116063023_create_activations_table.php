<?php

declare(strict_types=1);

use Databoss\ConnectionInterface;
use Kram\MigrationInterface;

class CreateActivationsTable implements MigrationInterface
{
    public function up(ConnectionInterface $connection): void
    {
        $connection->create('activations', [
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
                'primary' => true,
            ],
            'license_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'machine_id' => [
                'type' => 'VARCHAR(255)',
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR(45)',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'activated_at' => [
                'type' => 'DATETIME',
                'null' => false,
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

        $connection->index('activations', ['license_id'], 'idx_license_id');
        $connection->index('activations', ['machine_id'], 'idx_machine_id');
        $connection->index('activations', ['ip_address'], 'idx_ip_address');
        $connection->index('activations', ['is_active'], 'idx_is_active');

        $connection->foreign('activations', 'license_id', ['licenses', 'id'], 'fk_license');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->drop('activations');
    }
}
