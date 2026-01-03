<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\UserProfileInterface;
use App\Models\User;
use App\Models\UserProfile;

class UserProfileRepository implements UserProfileInterface
{

    public function create(array $data)
    {
        return UserProfile::create($data);
    }
    public function find($id): User
    {
        return User::with('profile')->find($id);
    }
}
