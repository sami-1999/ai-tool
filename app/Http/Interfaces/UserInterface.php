<?php
namespace App\Http\Interfaces;
use App\Models\User;

interface UserInterface
{
    public function create(array $data): User;
    public function findByEmail(string $email): ?User;
}
