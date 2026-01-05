<?php

namespace App\Http\Services;

use App\Http\Repositories\ProposalRepository;
use App\Http\Services\JobAnalysisService;
use App\Http\Services\ProjectMatchingService;
use App\Http\Services\ProposalGenerationService;

class ProposalService
{
    public function __construct(
        private ProposalRepository $proposalRepo,
        private JobAnalysisService $jobAnalysisService,
        private ProjectMatchingService $projectMatchingService,
        private ProposalGenerationService $proposalGenerationService
    ) {}

    public function generateProposal(string $userId, string $jobDescription, string $provider = null)
    {
        // This will orchestrate the entire proposal generation flow
        return $this->proposalGenerationService->generate($userId, $jobDescription, $provider);
    }

    public function getUserProposals(string $userId)
    {
        return $this->proposalRepo->getUserProposals($userId);
    }

    public function submitFeedback(string $proposalId, string $userId, bool $success)
    {
        return $this->proposalRepo->storeFeedback($proposalId, $userId, $success);
    }

    public function show(string $id)
    {
        return $this->proposalRepo->find($id);
    }
}
