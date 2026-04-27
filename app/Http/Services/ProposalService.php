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
        $result = $this->proposalGenerationService->generate($userId, $payload, $provider);
        
        // Word count guard — re-request if out of range (only if proposal was generated)
        if ($result['proposal_generated'] && isset($result['proposal'])) {
            $proposalText = $result['proposal']['content'] ?? '';
            $wordCount    = str_word_count($proposalText);

            if ($wordCount < 50 || $wordCount > 400) {
                \Illuminate\Support\Facades\Log::warning('Proposal word count out of range', [
                    'word_count' => $wordCount,
                    'user_id'    => $userId,
                ]);
                // Throw so the caller can retry with the same prompt
                throw new \RuntimeException(
                    "Generated proposal has {$wordCount} words. Expected 50–400. Retry."
                );
            }
        }
        
        return $result;
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

    /**
     * Parse structured JSON response from AI
     */
    private function parseAiResponse(string $rawResponse): array
    {
        // Strip markdown code fences if present
        $clean = preg_replace('/^```json\s*/i', '', trim($rawResponse));
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Illuminate\Support\Facades\Log::error('AI response JSON parse failed', [
                'raw'   => substr($rawResponse, 0, 500),
                'error' => json_last_error_msg(),
            ]);
            throw new \RuntimeException('AI returned invalid JSON: ' . json_last_error_msg());
        }

        // Critical risk — do not proceed
        if (($decoded['risk_assessment']['risk_level'] ?? '') === 'CRITICAL') {
            return [
                'should_send'  => false,
                'risk_level'   => 'CRITICAL',
                'proposal'     => null,
                'notes'        => $decoded['generation_meta']['notes'] ?? 'Job flagged as high risk',
            ];
        }

        return [
            'should_send'       => $decoded['generation_meta']['should_send'] ?? true,
            'risk_level'        => $decoded['risk_assessment']['risk_level'] ?? 'MEDIUM',
            'fake_signals'      => $decoded['risk_assessment']['fake_signals'] ?? [],
            'green_flags'       => $decoded['risk_assessment']['green_flags'] ?? [],
            'pain_point'        => $decoded['job_analysis']['primary_pain_point'] ?? '',
            'job_category'      => $decoded['job_analysis']['job_category'] ?? '',
            'fit_score'         => $decoded['fit_score']['overall'] ?? 0,
            'strongest_proof'   => $decoded['fit_score']['strongest_proof_point'] ?? '',
            'honest_gap'        => $decoded['fit_score']['honest_gap'] ?? 'None',
            'proposal_text'     => $decoded['proposal']['text'] ?? '',
            'word_count'        => $decoded['proposal']['word_count'] ?? 0,
            'hook'              => $decoded['proposal']['hook'] ?? '',
            'question'          => $decoded['proposal']['question'] ?? '',
            'confidence'        => $decoded['generation_meta']['confidence'] ?? 'MEDIUM',
            'notes'             => $decoded['generation_meta']['notes'] ?? '',
        ];
    }
}
