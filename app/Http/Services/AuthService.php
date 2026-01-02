<?php

namespace App\Http\Services;

use App\Http\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepo
    ) {}

    public function register($request)
    {

        $user = $this->userRepo->create([
            'name'     => $request['name'],
            'email'    => $request['email'],
            'password' => Hash::make($request['password']),
        ]);

        $token = $user->createToken('auth')->accessToken;

        return compact('user', 'token');
    }

    public function login($request)
    {
        $user = $this->userRepo->findByEmail($request['email']);

        if (!$user || !Hash::check($request['password'], $user['password'])) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials']
            ]);
        }

        return [
            'token' => $user->createToken('auth')->accessToken,
            'user' => $user
        ];
    }

    public function logout($user)
    {
        $user->tokens()->delete();
    }
}
