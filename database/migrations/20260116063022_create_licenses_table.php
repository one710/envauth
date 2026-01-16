<?php

declare(strict_types=1);

use Databoss\ConnectionInterface;
use Kram\MigrationInterface;

class CreateLicensesTable implements MigrationInterface
{
    public function up(ConnectionInterface $connection): void
    {
        $connection->create('licenses', [
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
                'primary' => true,
            ],
            'envato_purchase_code' => [
                'type' => 'VARCHAR(255)',
                'null' => false,
                'unique' => true,
            ],
            'envato_item_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'product_type' => [
                'type' => 'VARCHAR(20)',
                'null' => false,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
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

        $connection->index('licenses', ['envato_purchase_code'], 'idx_purchase_code');
        $connection->index('licenses', ['envato_item_id'], 'idx_item_id');
        $connection->index('licenses', ['product_type'], 'idx_product_type');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->drop('licenses');
    }
}
