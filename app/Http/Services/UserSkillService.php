<?php

namespace App\Http\Services;

use App\Http\Repositories\UserSkillRepository;

class UserSkillService
{
    public function __construct(
        private UserSkillRepository $userSkillRepo
    ) {}

    public function getUserSkills(string $userId)
    {
        return $this->userSkillRepo->getUserSkills($userId);
    }

    public function store(string $userId, array $data)
    {
        return $this->userSkillRepo->store($userId, $data);
    }

    public function delete(string $userId, string $skillId)
    {
        return $this->userSkillRepo->delete($userId, $skillId);
    }
}
