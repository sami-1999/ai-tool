<?php
namespace App\Http\Interfaces;
use App\Models\User;

interface SkillInterface
{
    public function store(array $data);
    public function find(string $id);
}
