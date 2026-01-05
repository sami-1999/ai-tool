<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $guarded = [];

    public function proposalRequest()
    {
        return $this->belongsTo(ProposalRequest::class);
    }

    public function feedback()
    {
        return $this->hasMany(ProposalFeedback::class);
    }
}
