<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProposalGenerateRequest;
use App\Http\Requests\ProposalFeedbackRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\ProposalService;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function __construct(
        private ProposalService $proposalService
    ) {}

    /**
     * Display a listing of user's proposals.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->proposalService->getUserProposals($userId);
        return ApiResponse::success($data, 'Proposals retrieved successfully');
    }

    /**
     * Generate a new proposal.
     */
    public function generate(ProposalGenerateRequest $request)
    {
        $userId = $request->user()->id;
        $validated = $request->validated();
        $jobDescription = $validated['job_description'];
        $provider = $validated['provider'] ?? null;
        
        $proposal = $this->proposalService->generateProposal($userId, $jobDescription, $provider);
        return ApiResponse::success($proposal, 'Proposal generated successfully', 201);
    }

    /**
     * Display the specified proposal.
     */
    public function show(string $id)
    {
        $data = $this->proposalService->show($id);
        return ApiResponse::success($data, 'Proposal retrieved successfully');
    }

    /**
     * Submit feedback for a proposal.
     */
    public function feedback(ProposalFeedbackRequest $request, string $id)
    {
        $userId = $request->user()->id;
        $success = $request->validated()['success'];
        
        $feedback = $this->proposalService->submitFeedback($id, $userId, $success);
        return ApiResponse::success($feedback, 'Feedback submitted successfully');
    }
}
