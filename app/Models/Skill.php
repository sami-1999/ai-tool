<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = [
        'name',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    /**
     * Users that have this skill
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skills')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }

    /**
     * User skills pivot records
     */
    public function userSkills()
    {
        return $this->hasMany(UserSkill::class);
    }

    /**
     * Projects that use this skill
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_skills');
    }
}
