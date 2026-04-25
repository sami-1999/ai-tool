<?php

namespace App\Http\Services;

use App\Http\Repositories\ProposalRepository;
use App\Http\Repositories\UserProfileRepository;
use App\Models\ProposalRequest;
use App\Models\SuccessfulProposalPattern;
use App\Models\UsageLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProposalGenerationService
{
    public function __construct(
        private ProposalRepository $proposalRepo,
        private UserProfileRepository $userProfileRepo,
        private JobAnalysisService $jobAnalysisService,
        private ProjectMatchingService $projectMatchingService,
        private PromptBuilder $promptBuilder,
        private OpenAIService $openAIService,
        private ClaudeService $claudeService,
        private GeminiService $geminiService,
        private GroqService $groqService,
        private JobFitScoringService $jobFitScoringService,
        private ProposalQualityService $proposalQualityService
    ) {}

    /**
     * Main proposal generation orchestrator
     * 
     * @param string $userId
     * @param array $payload
     * @param string $provider
     * @return array
     */
    public function generate(string $userId, array $payload, string $provider = null): array
    {
        return DB::transaction(function () use ($userId, $payload, $provider) {
            $jobDescription = $payload['job_description'];
            $jobContext = $this->extractJobContext($payload);
            $forceGenerate = (bool)($payload['force_generate'] ?? false);
            
            // 1. Validate usage limits
            $this->validateUsageLimits($userId);
            
            // 2. Analyze job description
            $jobAnalysis = $this->jobAnalysisService->analyze($jobDescription);

            // 2.1 Assess job authenticity and apply recommendation
            $riskAssessment = $this->assessJobAuthenticity($jobDescription, $jobContext);
            
            // 3. Store proposal request
            $proposalRequest = $this->storeProposalRequest($userId, $jobDescription, $jobAnalysis, $jobContext, $riskAssessment);

            // 3.1 Return early if AI recommends not applying and generation not forced
            if (!$riskAssessment['should_apply'] && !$forceGenerate) {
                $this->logUsage($userId, 'proposal_generation');

                return [
                    'proposal_generated' => false,
                    'risk_assessment' => $riskAssessment,
                    'apply_recommendation' => [
                        'should_apply' => false,
                        'message' => 'This job appears risky. Proposal generation skipped by default. You can set force_generate=true to generate anyway.'
                    ],
                    'job_analysis' => $jobAnalysis,
                    'matched_projects' => [],
                    'proposal' => null,
                    'tokens_used' => 0,
                    'provider_used' => null
                ];
            }
            
            // 4. Match projects or fallback to skills-only
            $matchedData = $this->projectMatchingService->matchProjects($userId, $jobAnalysis);
            
            // 5. Build ethical prompt with PromptBuilder
            $userProfile = $this->userProfileRepo->find($userId);
            $userProfileArray = $userProfile ? $userProfile->toArray() : [];
            
            // Load user skills if needed
            if (!isset($userProfileArray['skills'])) {
                $userProfileArray['skills'] = $this->getUserSkills($userId);
            }
            
            $prompt = $this->promptBuilder->buildEthicalPrompt(
                $userProfileArray,
                $jobAnalysis,
                $matchedData,
                $jobDescription,
                $userId,
                $jobContext,
                $riskAssessment
            );
            
            // 6. Choose AI provider and generate proposal
            $aiResponse = $this->generateWithProvider($prompt, $provider);

            // 6.1. Calculate job fit score
            $fitScore = $this->jobFitScoringService->calculateFitScore(
                $userId,
                $jobAnalysis,
                $jobContext,
                $jobContext['job_posted_at'] ?? null
            );

            // 6.2. Validate proposal quality
            $quality = $this->proposalQualityService->validate(
                $aiResponse['content'],
                $jobDescription
            );

            // 6.3. Retry if quality is poor
            if (!$quality['passed'] && $quality['regenerate']) {
                Log::warning('Proposal quality check failed, retrying', [
                    'failed_checks' => $quality['failed_checks'],
                    'score' => $quality['score']
                ]);

                $retryPrompt = $this->buildRetryPrompt($prompt, $quality['failed_checks'], $aiResponse['content']);
                $aiResponse = $this->generateWithProvider($retryPrompt, $provider);
                $quality = $this->proposalQualityService->validate($aiResponse['content'], $jobDescription);
            }
            
            if (!$aiResponse['success']) {
                throw new \Exception('Failed to generate proposal: ' . $aiResponse['error']);
            }
            
            // 7. Save proposal
            $proposal = $this->storeProposal($proposalRequest->id, $aiResponse, $quality['score']);
            
            // 8. Log usage
            $this->logUsage($userId, 'proposal_generation');
            
            return [
                'proposal_generated' => true,
                'proposal' => $proposal,
                'quality' => $quality,
                'fit_score' => $fitScore,
                'risk_assessment' => $riskAssessment,
                'apply_recommendation' => [
                    'should_apply' => $riskAssessment['should_apply'],
                    'message' => $riskAssessment['should_apply']
                        ? 'This job looks reasonably authentic. Applying is recommended.'
                        : 'Job looks risky, but proposal generated because force_generate=true.'
                ],
                'job_analysis' => $jobAnalysis,
                'matched_projects' => $matchedData,
                'tokens_used' => $aiResponse['tokens_used'],
                'provider_used' => $aiResponse['model_used'],
                'tips' => $fitScore['tips'],
            ];
        });
    }

    /**
     * Generate proposal with selected AI provider (refactored)
     */
    private function generateWithProvider(string $prompt, string $provider = null): array
    {
        $provider = $provider ?? config('ai.default_provider', 'groq');
        
        $providers = [
            'groq'   => [$this->groqService,   'services.groq.api_key'],
            'gemini' => [$this->geminiService, 'services.gemini.api_key'],
            'claude' => [$this->claudeService, 'services.claude.api_key'],
            'openai' => [$this->openAIService, 'services.openai.api_key'],
        ];
        
        $priority = array_merge([$provider], array_keys(array_diff_key($providers, [$provider => null])));
        
        foreach ($priority as $name) {
            if (!isset($providers[$name])) continue;
            [$service, $configKey] = $providers[$name];
            if (config($configKey)) {
                return $service->generateProposal($prompt);
            }
        }
        
        return $this->generateDemoProposal();
    }

    /**
     * Validate user usage limits
     */
    private function validateUsageLimits(string $userId): void
    {
        $todayUsage = UsageLog::where('user_id', $userId)
            ->where('request_type', 'proposal_generation')
            ->whereDate('created_at', today())
            ->sum('count');
        
        $dailyLimit = 10; // Configurable limit
        
        if ($todayUsage >= $dailyLimit) {
            throw new \Exception('Daily proposal generation limit reached. Please try again tomorrow.');
        }
    }

    /**
     * Store proposal request
     */
    private function storeProposalRequest(
        string $userId,
        string $jobDescription,
        array $jobAnalysis,
        array $jobContext = [],
        array $riskAssessment = []
    )
    {
        return ProposalRequest::create([
            'user_id' => $userId,
            'job_description' => $jobDescription,
            'detected_job_type' => $jobAnalysis['job_type'],
            'client_name' => $jobContext['client_name'] ?? null,
            'client_rating' => $jobContext['client_rating'] ?? null,
            'client_spending' => $jobContext['client_spending'] ?? null,
            'posted_job_type' => $jobContext['job_type'] ?? null,
            'budget' => $jobContext['budget'] ?? null,
            'risk_level' => $riskAssessment['risk_level'] ?? null,
            'should_apply' => $riskAssessment['should_apply'] ?? true,
            'risk_reasoning' => $riskAssessment['reasoning'] ?? null,
            'risk_score' => $riskAssessment['score'] ?? 0,
            'job_posted_at' => $jobContext['job_posted_at'] ?? null,
            'proposals_count' => $jobContext['proposals_count'] ?? null,
            'has_payment_verified' => $jobContext['has_payment_verified'] ?? null,
        ]);
    }

    private function extractJobContext(array $payload): array
    {
        return [
            'client_name' => $payload['client_name'] ?? null,
            'client_rating' => isset($payload['client_rating']) ? (float)$payload['client_rating'] : null,
            'client_spending' => $payload['client_spending'] ?? null,
            'job_type' => $payload['job_type'] ?? null,
            'budget' => $payload['budget'] ?? null,
            'job_posted_at' => $payload['job_posted_at'] ?? null,
            'proposals_count' => $payload['proposals_count'] ?? null,
            'has_payment_verified' => $payload['has_payment_verified'] ?? null,
        ];
    }

    private function assessJobAuthenticity(string $jobDescription, array $jobContext): array
    {
        $score = 0;
        $reasons = [];

        $rating = $jobContext['client_rating'] ?? null;
        if ($rating !== null) {
            if ($rating >= 4.5) {
                $score += 2;
                $reasons[] = 'Client rating is strong';
            } elseif ($rating < 3) {
                $score -= 2;
                $reasons[] = 'Client rating is low';
            }
        } else {
            $reasons[] = 'Client rating not provided';
        }

        $spending = strtolower((string)($jobContext['client_spending'] ?? ''));
        if ($spending !== '') {
            if (preg_match('/\$?([0-9]+(?:\.[0-9]+)?)(k)?/', $spending, $matches)) {
                $amount = (float)$matches[1];
                if (!empty($matches[2])) {
                    $amount *= 1000;
                }

                if ($amount >= 500) {
                    $score += 2;
                    $reasons[] = 'Client has meaningful spending history';
                } elseif ($amount < 50) {
                    $score -= 1;
                    $reasons[] = 'Client spending is very low';
                }
            }

            if (str_contains($spending, 'new') || str_contains($spending, '0') || str_contains($spending, 'no hire')) {
                $score -= 2;
                $reasons[] = 'Client appears new or has no hire history';
            }
        } else {
            $reasons[] = 'Client spending not provided';
        }

        $budget = strtolower((string)($jobContext['budget'] ?? ''));
        if ($budget !== '' && (str_contains($budget, 'unpaid') || str_contains($budget, 'commission only') || str_contains($budget, 'free trial'))) {
            $score -= 2;
            $reasons[] = 'Budget terms look unsafe';
        }

        if (!empty($jobContext['job_type']) && !empty($jobContext['budget'])) {
            $score += 1;
            $reasons[] = 'Job type and budget are clearly defined';
        }

        $description = strtolower($jobDescription);
        $suspiciousSignals = ['telegram', 'whatsapp', 'pay upfront', 'security deposit', 'outside upwork', 'free sample'];
        foreach ($suspiciousSignals as $signal) {
            if (str_contains($description, $signal)) {
                $score -= 3;
                $reasons[] = "Suspicious signal found: {$signal}";
            }
        }

        $riskLevel = 'medium';
        $shouldApply = true;

        if ($score <= -2) {
            $riskLevel = 'high';
            $shouldApply = false;
        } elseif ($score >= 3) {
            $riskLevel = 'low';
            $shouldApply = true;
        }

        $confidence = min(95, max(55, 55 + (abs($score) * 8)));

        return [
            'score' => $score,
            'risk_level' => $riskLevel,
            'should_apply' => $shouldApply,
            'confidence' => $confidence,
            'reasoning' => implode('; ', array_unique($reasons)),
        ];
    }

    /**
     * Store generated proposal
     */
    private function storeProposal(int $proposalRequestId, array $aiResponse, int $qualityScore = null)
    {
        return $this->proposalRepo->store([
            'proposal_request_id' => $proposalRequestId,
            'content' => $aiResponse['content'],
            'tokens_used' => $aiResponse['tokens_used'],
            'model_used' => $aiResponse['model_used'],
            'quality_score' => $qualityScore,
        ]);
    }

    /**
     * Log usage for analytics and abuse prevention
     */
    private function logUsage(string $userId, string $requestType): void
    {
        $today = today();
        
        $existingLog = UsageLog::where('user_id', $userId)
            ->where('request_type', $requestType)
            ->whereDate('date', $today)
            ->first();
        
        if ($existingLog) {
            $existingLog->increment('count');
        } else {
            UsageLog::create([
                'user_id' => $userId,
                'request_type' => $requestType,
                'count' => 1,
                'date' => $today
            ]);
        }
    }

    /**
     * Process feedback and update successful patterns (v2 Learning System)
     */
    public function processFeedback(int $proposalId, bool $success, string $userId): void
    {
        // Feedback is already persisted by ProposalRepository::storeFeedback().
        // Only process learning logic here.
        if ($success) {
            $this->extractSuccessfulPatterns($proposalId, $userId);
        }
    }

    /**
     * Extract successful patterns from winning proposals (with hook_opening_line)
     */
    private function extractSuccessfulPatterns(int $proposalId, string $userId): void
    {
        $proposal = DB::table('proposals')
            ->join('proposal_requests', 'proposals.proposal_request_id', '=', 'proposal_requests.id')
            ->where('proposals.id', $proposalId)
            ->select('proposals.content', 'proposal_requests.detected_job_type')
            ->first();

        if ($proposal) {
            $tone = $this->detectToneFromContent($proposal->content);
            $structureNotes = $this->extractStructureNotes($proposal->content);
            
            // Extract first sentence as hook (max 200 chars)
            $sentences = preg_split('/[.!?]+/', $proposal->content, 2);
            $hookOpeningLine = !empty($sentences[0]) ? substr(trim($sentences[0]), 0, 200) : null;

            SuccessfulProposalPattern::updateOrCreate(
                [
                    'user_id' => $userId,
                    'job_type' => $proposal->detected_job_type
                ],
                [
                    'tone' => $tone,
                    'structure_notes' => $structureNotes,
                    'hook_opening_line' => $hookOpeningLine,
                ]
            );
        }
    }

    /**
     * Detect tone from proposal content (fixed - not using forbidden words)
     */
    private function detectToneFromContent(string $content): string
    {
        $sentences = preg_split('/[.!?]+/', $content);
        $sentences = array_filter($sentences, fn($s) => trim($s) !== '');
        
        if (empty($sentences)) return 'balanced';
        
        $avgLength = array_sum(array_map('str_word_count', $sentences)) / count($sentences);
        
        if ($avgLength < 12) {
            return 'direct';
        }
        
        if (preg_match("/\b(i'd|i've|you'll|it's|that's|we're)\b/i", $content)) {
            return 'conversational';
        }
        
        if (preg_match('/\b(strategy|approach|framework|solution)\b/i', $content)) {
            return 'strategic';
        }
        
        return 'balanced';
    }

    /**
     * Extract structure notes from successful proposal
     */
    private function extractStructureNotes(string $content): string
    {
        $notes = [];
        
        // Check for question pattern
        if (strpos($content, '?') !== false) {
            $notes[] = 'Ends with engaging question';
        }
        
        // Check for specific experience mention
        if (preg_match('/\d+\s*(years?|projects?|clients?)/', $content)) {
            $notes[] = 'Includes specific metrics/experience';
        }
        
        // Check for project reference
        if (strpos($content, 'project') !== false || strpos($content, 'similar') !== false) {
            $notes[] = 'References relevant experience';
        }
        
        return implode(', ', $notes);
    }

    /**
     * Build retry prompt when quality validation fails
     */
    private function buildRetryPrompt(string $originalPrompt, array $failedChecks, string $previousOutput): string
    {
        $failedList = implode(', ', $failedChecks);

        return "Your previous proposal failed quality checks: {$failedList}\n\n" .
               "Previous bad output:\n{$previousOutput}\n\n" .
               "Now rewrite it fixing ONLY these issues. Keep everything else the same.\n\n" .
               $originalPrompt;
    }

    /**
     * Get user skills for prompt building
     */
    private function getUserSkills(string $userId): array
    {
        return DB::table('user_skills')
            ->join('skills', 'user_skills.skill_id', '=', 'skills.id')
            ->where('user_skills.user_id', $userId)
            ->select('skills.name', 'user_skills.proficiency_level')
            ->get()
            ->toArray();
    }

    /**
     * Generate demo proposal when no API keys are configured
     * Enhanced with human-like, job-winning techniques
     */
    private function generateDemoProposal(): array
    {
        $demoProposals = [
            "Hey! I saw you're looking for help with an e-commerce site. Actually, I just wrapped up a similar project for a fashion retailer where we boosted their conversion rate by 43% through better checkout flow and product recommendations. I'd approach your project by first mapping out the customer journey, then building a clean, fast interface that reduces cart abandonment. Quick question - are you planning to integrate with any existing inventory system, or is this a fresh start?",
            
            "Hi! Your web app project caught my eye. I've built similar platforms before - most recently a SaaS dashboard that now handles 2K daily active users with sub-2-second load times. I'd start by setting up a solid backend architecture that can scale, then focus on making the frontend intuitive and responsive. What's your timeline looking like for the MVP launch?",
            
            "Hello! So you need a mobile app for both iOS and Android. I've shipped 12 apps to production, including a fintech one that's sitting at 4.7 stars with 80K+ downloads. The approach I'd take is React Native for faster development, but native if performance is critical for your use case. What's more important to you - getting to market quickly or having that buttery-smooth native feel?",
            
            "Hey there! I read through your project requirements and the custom CRM you're describing sounds a lot like something I built for a real estate agency last year. That one cut their admin time by 35% and they're still using it daily. I'd focus on automation for the repetitive stuff first, then add the reporting dashboard. By the way, what's the biggest pain point in your current workflow that you're hoping this will solve?",
            
            "Hi! Your WordPress project looks interesting. I've customized and optimized dozens of WP sites, and one I worked on last month went from 6-second load time down to under 2 seconds - huge difference in their bounce rate. I'd clean up your existing theme, optimize the images and database, then add the new features you mentioned. Are you planning to handle the content migration yourself or would you need help with that too?"
        ];

        return [
            'content' => $demoProposals[array_rand($demoProposals)],
            'tokens_used' => 0,
            'model_used' => 'demo-generator',
            'success' => true
        ];
    }
}
