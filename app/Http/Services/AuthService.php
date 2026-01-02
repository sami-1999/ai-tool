<?php

namespace App\Http\Services;

use App\Http\Interfaces\UserInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserInterface $userRepo
    ) {}

    public function register($request)
    {

        $user = $this->userRepo->create($request);

        $token = $user->createToken('auth')->accessToken;

        return compact('user', 'token');
    }

    public function login($request)
    {
        if (!Auth::attempt(['email'    => $request['email'], 'password' => $request['password']])) {

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
            
        }

        $user = Auth::user();

        $token = $user->createToken('auth')->accessToken;

        return compact('user', 'token');
    }

    public function logout($user)
    {
        $user->currentAccessToken()->revoke();
    }
}
