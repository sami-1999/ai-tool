<?php

namespace App\Http\Services;

use App\Http\Repositories\ProjectRepository;

class ProjectService
{
    public function __construct(
        private ProjectRepository $projectRepo
    ) {}

    public function store(array $data)
    {
        return $this->projectRepo->store($data);
    }

    public function show(string $id)
    {
        return $this->projectRepo->find($id);
    }

    public function update(array $data, string $id)
    {
        return $this->projectRepo->update($data, $id);
    }

    public function delete(string $id)
    {
        return $this->projectRepo->delete($id);
    }

    public function getUserProjects(string $userId)
    {
        return $this->projectRepo->getUserProjects($userId);
    }
}
