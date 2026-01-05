<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalRequest extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }
}
