<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIntegration extends Model
{
    protected $fillable = [
        'project_id',
        'integration_name'
    ];

    /**
     * Get the project that this integration belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
