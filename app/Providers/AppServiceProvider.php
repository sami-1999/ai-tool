<?php

namespace App\Providers;

use App\Http\Interfaces\UserInterface;
use App\Http\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        app()->bind(
            UserInterface::class,
            UserRepository::class
        );
        app()->bind(
            'App\Http\Interfaces\UserProfileInterface',
            'App\Http\Repositories\UserProfileRepository'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url')
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($user->email);
        });
    }
}
