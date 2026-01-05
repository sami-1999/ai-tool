<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\ProposalController;
use App\Http\Controllers\API\SkillController;
use App\Http\Controllers\API\TestController;
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

// Test Routes - Public access for AI testing
Route::prefix('test')->controller(TestController::class)->group(function () {
    Route::get('/openai', 'testOpenAI');
    Route::post('/proposal-generation', 'testProposalGeneration');
    Route::get('/claude', 'testClaude');
    Route::post('/claude-proposal-generation', 'testClaudeProposalGeneration');
    Route::post('/compare-providers', 'compareProviders');
});

Route::middleware('auth:api')->group(function () {

    Route::prefix('user')->controller(UserProfileController::class)->group(function () {
        Route::put('/profile/{id}', 'updateProfile');
        Route::get('/profile/{id}', 'profile');
    });

    Route::apiResource('skill', SkillController::class);
    Route::apiResource('project', ProjectController::class);
    
    Route::prefix('proposals')->controller(ProposalController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/generate', 'generate');
        Route::get('/{id}', 'show');
        Route::post('/{id}/feedback', 'feedback');
    });
});
