<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\ProjectInterface;
use App\Models\Project;

class ProjectRepository implements ProjectInterface
{
    public function store(array $data)
    {
        return Project::create($data);
    }

    public function find(string $id)
    {
        return Project::findOrFail($id);
    }

    public function update(array $data, string $id)
    {
        $project = $this->find($id);
        if ($project) {
            $project->update($data);
        }
        return $project;
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
        return Project::where('user_id', $userId)->get();
    }
}
