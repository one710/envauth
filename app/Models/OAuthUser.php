<?php

declare(strict_types=1);

namespace App\Models;

use Datum\Model;

class OAuthUser extends Model
{
    protected static ?string $table = 'oauth_users';

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return strtotime($this->token_expires_at) < time();
    }
}
