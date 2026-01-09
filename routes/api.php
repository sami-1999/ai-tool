<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\ProposalController;
use App\Http\Controllers\API\SkillController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\API\UserSkillController;
use Illuminate\Support\Facades\Route;


Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:api');
    Route::post('forgot-password', 'sendResetLink');
    Route::post('reset-password', 'resetPassword');
});

Route::middleware('auth:api')->group(function () {

    Route::prefix('user')->controller(UserProfileController::class)->group(function () {
        Route::put('/profile/{id}', 'updateProfile');
        Route::get('/profile/{id}', 'profile');
    });

    Route::apiResource('skill', SkillController::class);
    Route::apiResource('project', ProjectController::class);
    Route::apiResource('user-skills', UserSkillController::class)->only(['index', 'store', 'destroy'])->parameters(['user-skills' => 'skillId']);

    Route::prefix('proposals')->controller(ProposalController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/generate', 'generate');
        Route::get('/{id}', 'show');
        Route::post('/{id}/feedback', 'feedback');
    });
});
