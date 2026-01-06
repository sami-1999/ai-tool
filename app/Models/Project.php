<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'industry',
        'challenges',
        'outcome'
    ];

    /**
     * Get the user that owns the project
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the skills associated with this project
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'project_skills');
    }

    /**
     * Get the integrations for this project
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(ProjectIntegration::class);
    }

    /**
     * Get integration names as array
     */
    public function getIntegrationNamesAttribute()
    {
        return $this->integrations->pluck('integration_name')->toArray();
    }

    /**
     * Get skill names as array
     */
    public function getSkillNamesAttribute()
    {
        return $this->skills->pluck('name')->toArray();
    }
}
