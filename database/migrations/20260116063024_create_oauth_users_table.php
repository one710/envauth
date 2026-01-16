<?php

declare(strict_types=1);

use Databoss\ConnectionInterface;
use Kram\MigrationInterface;

class CreateOauthUsersTable implements MigrationInterface
{
    public function up(ConnectionInterface $connection): void
    {
        $connection->create('oauth_users', [
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
                'primary' => true,
            ],
            'envato_user_id' => [
                'type' => 'VARCHAR(255)',
                'null' => false,
                'unique' => true,
            ],
            'username' => [
                'type' => 'VARCHAR(255)',
                'null' => false,
            ],
            'email' => [
                'type' => 'VARCHAR(255)',
                'null' => true,
            ],
            'access_token' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'refresh_token' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'token_expires_at' => [
                'type' => 'DATETIME',
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

        $connection->index('oauth_users', ['envato_user_id'], 'idx_envato_user_id');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->drop('oauth_users');
    }
}
