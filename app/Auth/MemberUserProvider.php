<?php

namespace App\Auth;

use App\Models\Member;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Members are found by the keyed hash of their email, because the address
 * itself is stored encrypted and cannot be searched.
 */
class MemberUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        return is_string($email) && $email !== '' ? Member::findByEmail($email) : null;
    }
}
