<?php

namespace App\Http\Services;
use App\Http\Repositories\UserProfileRepository;


class UserProfile
{
    public function __construct(
        private UserProfileRepository $userRepo
    ) {}

    public function create(array $data)
    {
        return $this->userRepo->create($data);
    }

    public function profile($id)
    {
        return $this->userRepo->find($id);
    }
}
