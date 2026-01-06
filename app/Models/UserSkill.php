<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    protected $fillable = [
        'user_id',
        'skill_id',
        'proficiency_level'
    ];

    protected $casts = [
        'proficiency_level' => 'string'
    ];

    /**
     * Get the user that owns the skill
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the skill details
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
