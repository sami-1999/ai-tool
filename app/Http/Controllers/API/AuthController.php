<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Services\AuthService;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
        $data = $this->authService->register($validated);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user'  => $data['user'],
                'token' => $data['token'],
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $data = $this->authService->login($validated);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user'  => $data['user'],
                'token' => $data['token'],
            ],
        ], 201);;
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out']);
    }
}
