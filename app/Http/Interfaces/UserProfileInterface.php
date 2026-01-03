<?php
namespace App\Http\Interfaces;
use App\Models\User;

interface UserProfileInterface
{
    public function create(array $data);
    public function find(array $data): User;
}
