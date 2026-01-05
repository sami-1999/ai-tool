<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\SkillInterface;
use App\Models\Skill;

class SkillRepository implements SkillInterface
{
    public function getAll()
    {
        return Skill::all();
    }

    public function store(array $data)
    {
        return Skill::create($data);
    }
    public function find(string $id)
    {
        return Skill::findOrFail($id);
    }
    public function update(array $data, string $id)
    {
        $skill = $this->find($id);
        if ($skill) {
            $skill->name = $data['name'] ?? $skill->name;
            $skill->save();
        }
        return $skill;
    }

    public function delete(string $id)
    {
        $skill = $this->find($id);
        if ($skill) {
            $skill->delete();
        }
        return $skill;
    }
}
