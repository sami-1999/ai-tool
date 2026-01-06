<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\ProjectInterface;
use App\Models\Project;

class ProjectRepository implements ProjectInterface
{
    public function store(array $data)
    {
        $skills = $data['skills'] ?? [];
        unset($data['skills']);
        
        $project = Project::create($data);
        
        if (!empty($skills)) {
            $project->skills()->attach($skills);
        }
        
        return $project->load('skills');
    }

    public function find(string $id)
    {
        return Project::with('skills')->findOrFail($id);
    }

    public function update(array $data, string $id)
    {
        $skills = $data['skills'] ?? [];
        unset($data['skills']);
        
        $project = Project::findOrFail($id);
        $project->update($data);
        
        if (isset($data['skills']) || !empty($skills)) {
            $project->skills()->sync($skills);
        }
        
        return $project->load('skills');
    }

    public function delete(string $id)
    {
        $project = $this->find($id);
        if ($project) {
            $project->delete();
        }
        return $project;
    }

    public function getUserProjects(string $userId)
    {
        return Project::with('skills')->where('user_id', $userId)->get();
    }
}
