<?php

namespace App\Http\Interfaces;

interface ProjectInterface
{
    public function store(array $data);
    public function find(string $id);
    public function update(array $data, string $id);
    public function delete(string $id);
    public function getUserProjects(string $userId);
}
