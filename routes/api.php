<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;


Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:api');
    Route::post('forgot-password', 'sendResetLink');
    Route::post('reset-password', 'resetPassword');
});

Route::controller(UserProfileController::class)->middleware('auth:api')->group(function () {
    Route::get('/user/{id}/profile', 'profile');
    Route::post('/change-password', 'changePassword');
});
