<?php

namespace App\Http\Interfaces;

interface UserSkillInterface
{
    public function getUserSkills(string $userId);
    public function store(string $userId, array $data);
    public function delete(string $userId, string $skillId);
}
