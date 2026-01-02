<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function sendPasswordResetLink(array $data)
    {
        return Password::sendResetLink(['email' => $data['email']]);
    }

    public function resetPassword(array $data): string
    {
       
        return Password::reset($data, function ($user, $password) {
            $user->update([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60)
            ]);
        });
    }
}
