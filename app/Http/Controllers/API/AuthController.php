<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordEmailRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Services\AuthService;
use App\Http\Services\PasswordResetService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PasswordResetService $passwordResetService
    ) {}

    public function register(RegisterRequest $request)
    {
        $data = $this->authService->register($request->validated());
        return ApiResponse::success($data, 'Registration successful', 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $this->authService->login($request->validated());
        return ApiResponse::success($data, 'Login successful');
    }

    public function sendResetLink(PasswordEmailRequest $request)
    {
        $status = $this->passwordResetService->sendPasswordResetLink($request->validated());
        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::success(null, 'Password reset link sent to your email');
        }
        return ApiResponse::error(__($status), 400);
    }

    public function resetPassword(PasswordResetRequest $request)
    {
        $status = $this->passwordResetService->resetPassword($request->validated());
        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Password reset successfully');
        }
        return ApiResponse::error(__($status), 400);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return ApiResponse::success(null, 'Logged out successfully');
    }
}
