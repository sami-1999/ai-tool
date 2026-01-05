<?php

namespace App\Http\Services;

use App\Http\Repositories\SkillRepository;
use Illuminate\Support\Arr;

class SkillService
{
    public function __construct(
        private SkillRepository $skillRepo
    ) {}

    public function index()
    {
        return $this->skillRepo->getAll();
    }

    public function store(array $data)
    {
        return $this->skillRepo->store($data);
    }

    public function show(string $id)
    {
        return $this->skillRepo->find($id);
    }

    public function update(array $data, string $id)
    {
        return $this->skillRepo->update($data, $id);
    }

    public function delete(string $id)
    {
        return $this->skillRepo->delete($id);
    }
    
}
