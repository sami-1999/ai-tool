<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\UserProfileInterface;
use App\Models\User;

class UserProfileRepository implements UserProfileInterface
{
    public function find($id): User
    {
        return User::with('profile')->find($id);
    }
}
