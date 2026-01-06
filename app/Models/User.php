<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * User skills relationship
     */
    public function userSkills()
    {
        return $this->hasMany(UserSkill::class);
    }

    /**
     * User skills with skill details
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }

    /**
     * User projects
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Proposal requests
     */
    public function proposalRequests()
    {
        return $this->hasMany(ProposalRequest::class);
    }

    /**
     * Successful proposal patterns
     */
    public function successfulProposalPatterns()
    {
        return $this->hasMany(SuccessfulProposalPattern::class);
    }

    /**
     * Usage logs
     */
    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class);
    }
}
