<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\UserSkillInterface;
use App\Models\UserSkill;

class UserSkillRepository implements UserSkillInterface
{
    public function getUserSkills(string $userId)
    {
        return UserSkill::with('skill')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($userSkill) {
                return [
                    'id' => $userSkill->id,
                    'skill_id' => $userSkill->skill_id,
                    'skill_name' => $userSkill->skill->name,
                    'proficiency_level' => $userSkill->proficiency_level,
                ];
            });
    }

    public function store(string $userId, array $data)
    {
        if (isset($data['skills'])) {
            $addedSkills = [];
            foreach ($data['skills'] as $skillData) {
                $userSkill = UserSkill::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'skill_id' => $skillData['skill_id']
                    ],
                    [
                        'proficiency_level' => $skillData['proficiency_level']
                    ]
                );
                $userSkill->load('skill');
                $addedSkills[] = [
                    'id' => $userSkill->id,
                    'skill_id' => $userSkill->skill_id,
                    'skill_name' => $userSkill->skill->name,
                    'proficiency_level' => $userSkill->proficiency_level,
                ];
            }
            return $addedSkills;
        }

        $userSkill = UserSkill::updateOrCreate(
            [
                'user_id' => $userId,
                'skill_id' => $data['skill_id']
            ],
            [
                'proficiency_level' => $data['proficiency_level']
            ]
        );

        $userSkill->load('skill');
        return [
            'id' => $userSkill->id,
            'skill_id' => $userSkill->skill_id,
            'skill_name' => $userSkill->skill->name,
            'proficiency_level' => $userSkill->proficiency_level,
        ];
    }

    public function delete(string $userId, string $skillId)
    {
        $userSkill = UserSkill::where('user_id', $userId)
            ->where('skill_id', $skillId)
            ->first();

        if (!$userSkill) {
            return null;
        }

        $userSkill->delete();
        return true;
    }
}
