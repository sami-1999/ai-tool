<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalFeedback extends Model
{
    protected $guarded = [];

    protected $casts = [
        'success' => 'boolean'
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
