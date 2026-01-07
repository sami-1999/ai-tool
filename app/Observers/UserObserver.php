<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Auto-create user profile with default values when user registers
        $user->profile()->create([
            'title' => 'New Freelancer',
            'years_experience' => 0,
            'default_tone' => 'professional',
            'writing_style_notes' => null,
            'bio' => null,
            'birthday' => null,
            'country' => null,
            'city' => null,
            'address' => null,
            'portfolio_site_link' => null,
            'github_link' => null,
            'linkedin_link' => null,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
