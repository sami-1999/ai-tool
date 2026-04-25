<?php

namespace App\Http\Services;

use App\Http\Repositories\ProposalRepository;

class ProposalService
{
    public function __construct(
        private ProposalRepository $proposalRepo,
        private ProposalGenerationService $proposalGenerationService
    ) {}

    public function generateProposal(string $userId, array $payload, string $provider = null)
    {
        // This will orchestrate the entire proposal generation flow
        return $this->proposalGenerationService->generate($userId, $payload, $provider);
    }

    public function getUserProposals(string $userId)
    {
        return $this->proposalRepo->getUserProposals($userId);
    }

    public function submitFeedback(string $proposalId, string $userId, bool $success)
    {
        $feedback = $this->proposalRepo->storeFeedback($proposalId, $userId, $success);
        
        // Trigger the learning system for successful proposals
        if ($success === true) {
            $this->proposalGenerationService->processFeedback((int)$proposalId, $success, $userId);
        }
        
        return $feedback;
    }

    public function show(string $id, string $userId)
    {
        $proposal = $this->proposalRepo->find($id);
        
        // Authorization check - verify the proposal belongs to the user
        if ($proposal && $proposal->proposal_request_user_id !== $userId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to view this proposal.');
        }
        
        return $proposal;
    }
}
