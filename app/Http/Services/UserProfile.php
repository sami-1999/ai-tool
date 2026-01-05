<?php

namespace App\Http\Services;
use App\Http\Repositories\UserProfileRepository;


class UserProfile
{
    public function __construct(
        private UserProfileRepository $userRepo
    ) {}

    public function update(array $data, $id)
    {
        return $this->userRepo->update($data, $id);
    }

    public function profile($id)
    {
        return $this->userRepo->find($id);
    }
}
