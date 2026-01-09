<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\UserSkillInterface;
use App\Models\Skill;
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
                // Handle inline skill creation
                $skillId = $this->resolveSkillId($skillData);
                
                $userSkill = UserSkill::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'skill_id' => $skillId
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

        // Handle inline skill creation for single skill
        $skillId = $this->resolveSkillId($data);
        
        $userSkill = UserSkill::updateOrCreate(
            [
                'user_id' => $userId,
                'skill_id' => $skillId
            ],
            [
                'proficiency_level' => $data['proficiency_level']
            ]
        );

        // Refresh the model to ensure relationships are loaded properly
        $userSkill->refresh();
        $userSkill->load('skill');
        
        return [
            'id' => $userSkill->id,
            'skill_id' => $userSkill->skill_id,
            'skill_name' => $userSkill->skill ? $userSkill->skill->name : 'Unknown Skill',
            'proficiency_level' => $userSkill->proficiency_level,
        ];
    }

    /**
     * Resolve skill ID from data - either use existing or create new skill
     */
    private function resolveSkillId(array $data): int
    {
        // If skill_id is provided, use it
        if (!empty($data['skill_id'])) {
            return $data['skill_id'];
        }

        // If skill_name is provided, find or create the skill
        if (!empty($data['skill_name'])) {
            $skill = Skill::firstOrCreate(
                ['name' => trim($data['skill_name'])],
                ['status' => true]
            );
            return $skill->id;
        }

        throw new \Exception('Either skill_id or skill_name must be provided');
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
