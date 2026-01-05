<?php
namespace App\Http\Interfaces;
use App\Models\User;

interface SkillInterface
{
    public function getAll();
    public function store(array $data);
    public function find(string $id);
    public function update(array $data, string $id);
    public function delete(string $id);
}
