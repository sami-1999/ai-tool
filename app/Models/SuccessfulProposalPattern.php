<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuccessfulProposalPattern extends Model
{
    protected $fillable = [
        'user_id',
        'job_type',
        'tone',
        'structure_notes'
    ];

    /**
     * Get the user that this pattern belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
