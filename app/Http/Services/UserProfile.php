<?php

namespace App\Http\Services;
use App\Http\Repositories\UserProfileRepository;


class UserProfile
{
    public function __construct(
        private UserProfileRepository $userRepo
    ) {}

    public function profile($id)
    {
        return $this->userRepo->find($id);
    }
}
