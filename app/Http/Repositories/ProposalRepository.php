<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\ProposalInterface;
use App\Models\Proposal;
use App\Models\ProposalFeedback;

class ProposalRepository implements ProposalInterface
{
    public function store(array $data)
    {
        return Proposal::create($data);
    }

    public function find(string $id)
    {
        return Proposal::findOrFail($id);
    }

    public function getUserProposals(string $userId)
    {
        return Proposal::whereHas('proposalRequest', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('proposalRequest')->get();
    }

    public function storeFeedback(string $proposalId, string $userId, bool $success)
    {
        return ProposalFeedback::create([
            'proposal_id' => $proposalId,
            'user_id' => $userId,
            'success' => $success
        ]);
    }
}
