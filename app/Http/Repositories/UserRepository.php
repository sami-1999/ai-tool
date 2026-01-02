<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\UserInterface;
use App\Models\User;

class UserRepository implements UserInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::whereEmail($email)->first();
    }
}
