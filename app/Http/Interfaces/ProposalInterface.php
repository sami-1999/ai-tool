<?php

namespace App\Http\Interfaces;

interface ProposalInterface
{
    public function store(array $data);
    public function find(string $id);
    public function getUserProposals(string $userId);
    public function storeFeedback(string $proposalId, string $userId, bool $success);
}
