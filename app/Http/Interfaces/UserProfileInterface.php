<?php
namespace App\Http\Interfaces;
use App\Models\User;

interface UserProfileInterface
{
    public function update(array $data, $id);
    public function find($id): User;
}
