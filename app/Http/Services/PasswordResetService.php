<?php

namespace App\Http\Services;

use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function sendPasswordResetLink(array $email)
    {
        // return Password::sendResetLink($email);

        $status = Password::sendResetLink($email);

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error(__($status), 400);
        }
    }

    public function resetPassword(array $data)
    {
        $status = Password::reset($data, function ($user, $password) {
            $user->update([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60)
            ]);
        });
        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__($status), 400);
        }
    }
}
