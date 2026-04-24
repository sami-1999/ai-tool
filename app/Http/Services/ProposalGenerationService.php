<?php

namespace App\Http\Services;

use App\Http\Repositories\ProposalRepository;
use App\Http\Repositories\UserProfileRepository;
use App\Http\Services\JobAnalysisService;
use App\Http\Services\ProjectMatchingService;
use App\Http\Services\PromptBuilder;
use App\Http\Services\OpenAIService;
use App\Http\Services\ClaudeService;
use App\Http\Services\GeminiService;
use App\Http\Services\GroqService;
use App\Models\ProposalRequest;
use App\Models\ProposalFeedback;
use App\Models\SuccessfulProposalPattern;
use App\Models\UsageLog;
use Illuminate\Support\Facades\DB;

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
        private GroqService $groqService
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
            
            if (!$aiResponse['success']) {
                throw new \Exception('Failed to generate proposal: ' . $aiResponse['error']);
            }
            
            // 7. Save proposal
            $proposal = $this->storeProposal($proposalRequest->id, $aiResponse);
            
            // 8. Log usage
            $this->logUsage($userId, 'proposal_generation');
            
            return [
                'proposal_generated' => true,
                'proposal' => $proposal,
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
                'provider_used' => $aiResponse['model_used']
            ];
        });
    }

    /**
     * Generate proposal with selected AI provider
     */
    private function generateWithProvider(string $prompt, string $provider = null): array
    {
        // Default provider from config or fallback
        $provider = $provider ?? config('ai.default_provider', 'groq');
        
        // Check if any API keys are configured
        $claudeKey = config('services.claude.api_key');
        $openaiKey = config('services.openai.api_key');
        $geminiKey = config('services.gemini.api_key');
        $groqKey = config('services.groq.api_key');
        
        if (!$claudeKey && !$openaiKey && !$geminiKey && !$groqKey) {
            // Return demo proposal if no API keys configured
            return $this->generateDemoProposal();
        }
        
        switch ($provider) {
            case 'groq':
                if (!$groqKey) {
                    // Fallback to Gemini then Claude then OpenAI if Groq not configured
                    if ($geminiKey) {
                        return $this->geminiService->generateProposal($prompt);
                    } elseif ($claudeKey) {
                        return $this->claudeService->generateProposal($prompt);
                    } elseif ($openaiKey) {
                        return $this->openAIService->generateProposal($prompt);
                    }
                    return $this->generateDemoProposal();
                }
                return $this->groqService->generateProposal($prompt);

            case 'gemini':
                if (!$geminiKey) {
                    // Fallback to Groq then Claude then OpenAI if Gemini not configured
                    if ($groqKey) {
                        return $this->groqService->generateProposal($prompt);
                    } elseif ($claudeKey) {
                        return $this->claudeService->generateProposal($prompt);
                    } elseif ($openaiKey) {
                        return $this->openAIService->generateProposal($prompt);
                    }
                    return $this->generateDemoProposal();
                }
                return $this->geminiService->generateProposal($prompt);
                
            case 'claude':
                if (!$claudeKey) {
                    // Fallback to Groq then Gemini then OpenAI if Claude not configured
                    if ($groqKey) {
                        return $this->groqService->generateProposal($prompt);
                    } elseif ($geminiKey) {
                        return $this->geminiService->generateProposal($prompt);
                    } elseif ($openaiKey) {
                        return $this->openAIService->generateProposal($prompt);
                    }
                    return $this->generateDemoProposal();
                }
                return $this->claudeService->generateProposal($prompt);
                
            case 'openai':
                if (!$openaiKey) {
                    // Fallback to Groq then Gemini then Claude if OpenAI not configured
                    if ($groqKey) {
                        return $this->groqService->generateProposal($prompt);
                    } elseif ($geminiKey) {
                        return $this->geminiService->generateProposal($prompt);
                    } elseif ($claudeKey) {
                        return $this->claudeService->generateProposal($prompt);
                    }
                    return $this->generateDemoProposal();
                }
                return $this->openAIService->generateProposal($prompt);
                
            default:
                // Try Groq first, then Gemini, then Claude, then OpenAI, then demo
                if ($groqKey) {
                    return $this->groqService->generateProposal($prompt);
                } elseif ($geminiKey) {
                    return $this->geminiService->generateProposal($prompt);
                } elseif ($claudeKey) {
                    return $this->claudeService->generateProposal($prompt);
                } elseif ($openaiKey) {
                    return $this->openAIService->generateProposal($prompt);
                } else {
                    return $this->generateDemoProposal();
                }
        }
    }

    /**
     * Build AI prompt based on user data and job analysis
     */
    private function buildPrompt(string $userId, array $jobAnalysis, array $matchedData, string $jobDescription): string
    {
        $userProfile = $this->userProfileRepo->find($userId);
        
        // Get past successful proposals for learning
        $successfulProposals = $this->getSuccessfulProposals($userId);
        
        $prompt = "Generate a personalized Upwork proposal based on the following:\n\n";
        
        // Freelancer background
        $prompt .= "FREELANCER BACKGROUND:\n";
        $prompt .= "- Title: " . ($userProfile->title ?? 'Freelancer') . "\n";
        $prompt .= "- Experience: " . ($userProfile->years_experience ?? '2') . " years\n";
        $prompt .= "- Tone: " . ($userProfile->default_tone ?? 'Professional') . "\n";
        
        if (isset($matchedData['type']) && $matchedData['type'] === 'skills_only') {
            // Skills-only fallback
            $prompt .= "- Using skills-based approach (no previous projects)\n";
            $prompt .= "- Writing style notes: " . ($userProfile->writing_style_notes ?? 'None') . "\n";
        } else {
            // Project-based approach
            $prompt .= "\nRELEVANT PROJECTS:\n";
            foreach ($matchedData as $index => $scoredProject) {
                $project = $scoredProject['project'];
                $prompt .= ($index + 1) . ". " . $project->title . "\n";
                $prompt .= "   Description: " . substr($project->description, 0, 150) . "...\n";
                $prompt .= "   Outcome: " . ($project->outcome ?? 'Successful completion') . "\n";
                if ($scoredProject['skill_matches'] > 0) {
                    $prompt .= "   Skill matches: " . $scoredProject['skill_matches'] . "\n";
                }
                $prompt .= "\n";
            }
        }
        
        // Add successful proposal patterns if available
        if (!empty($successfulProposals)) {
            $prompt .= "\nSUCCESSFUL PROPOSAL PATTERNS (for reference):\n";
            foreach ($successfulProposals as $successful) {
                $prompt .= "- " . substr($successful->content, 0, 100) . "...\n";
            }
        }
        
        // Rules and job description
        $prompt .= "\nRULES:\n";
        $prompt .= "- Maximum 120 words\n";
        $prompt .= "- Human, conversational tone\n";
        $prompt .= "- No buzzwords or clichés\n";
        $prompt .= "- Ask 1 smart, relevant question\n";
        $prompt .= "- Reference specific project experience when possible\n";
        $prompt .= "- Show genuine understanding of the client's needs\n";
        
        $prompt .= "\nJOB DESCRIPTION:\n";
        $prompt .= $jobDescription . "\n\n";
        
        $prompt .= "Generate a compelling, personalized proposal:";
        
        return $prompt;
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
    private function storeProposal(int $proposalRequestId, array $aiResponse)
    {
        return $this->proposalRepo->store([
            'proposal_request_id' => $proposalRequestId,
            'content' => $aiResponse['content'],
            'tokens_used' => $aiResponse['tokens_used'],
            'model_used' => $aiResponse['model_used']
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
     * Get past successful proposals for learning
     */
    private function getSuccessfulProposals(string $userId): array
    {
        // Get proposals that received positive feedback
        $successfulProposals = DB::table('proposals')
            ->join('proposal_requests', 'proposals.proposal_request_id', '=', 'proposal_requests.id')
            ->join('proposal_feedback', 'proposals.id', '=', 'proposal_feedback.proposal_id')
            ->where('proposal_requests.user_id', $userId)
            ->where('proposal_feedback.success', true)
            ->select('proposals.content')
            ->limit(3)
            ->get();
        
        return $successfulProposals->toArray();
    }

    /**
     * Process feedback and update successful patterns (v2 Learning System)
     */
    public function processFeedback(int $proposalId, bool $success, string $userId): void
    {
        // Store feedback
        ProposalFeedback::create([
            'proposal_id' => $proposalId,
            'user_id' => $userId,
            'success' => $success
        ]);

        // If successful, extract patterns for future learning
        if ($success) {
            $this->extractSuccessfulPatterns($proposalId, $userId);
        }
    }

    /**
     * Extract successful patterns from winning proposals
     */
    private function extractSuccessfulPatterns(int $proposalId, string $userId): void
    {
        $proposal = DB::table('proposals')
            ->join('proposal_requests', 'proposals.proposal_request_id', '=', 'proposal_requests.id')
            ->where('proposals.id', $proposalId)
            ->select('proposals.content', 'proposal_requests.detected_job_type')
            ->first();

        if ($proposal) {
            // Simple pattern extraction (can be enhanced with NLP)
            $tone = $this->detectToneFromContent($proposal->content);
            $structureNotes = $this->extractStructureNotes($proposal->content);

            SuccessfulProposalPattern::updateOrCreate(
                [
                    'user_id' => $userId,
                    'job_type' => $proposal->detected_job_type
                ],
                [
                    'tone' => $tone,
                    'structure_notes' => $structureNotes
                ]
            );
        }
    }

    /**
     * Detect tone from proposal content
     */
    private function detectToneFromContent(string $content): string
    {
        $content = strtolower($content);
        
        if (strpos($content, 'excited') !== false || strpos($content, 'love') !== false) {
            return 'enthusiastic';
        } elseif (strpos($content, 'professional') !== false || strpos($content, 'experience') !== false) {
            return 'professional';
        } elseif (strpos($content, 'hi there') !== false || strpos($content, 'hey') !== false) {
            return 'friendly';
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
     */
    private function generateDemoProposal(): array
    {
        $demoProposals = [
            "Hi there! I've been helping businesses create engaging websites for over 3 years, and your project caught my attention. I recently completed a similar e-commerce site that increased conversion rates by 40%. I'd love to understand more about your target audience and specific functionality requirements. Can we schedule a quick call to discuss your vision?",
            
            "Hello! Your project aligns perfectly with my expertise in full-stack development. I've delivered 15+ web applications using similar technologies, including a recent project that reduced loading time by 60%. I'm particularly interested in understanding your scalability requirements. What's your expected user growth over the next year?",
            
            "Hi! I specialize in creating user-friendly mobile applications and have successfully launched 10+ apps on both iOS and Android. Your project requirements remind me of a recent fintech app I built that now has 50K+ active users. I'd like to discuss your monetization strategy and target demographics. When would be a good time to connect?"
        ];

        return [
            'content' => $demoProposals[array_rand($demoProposals)],
            'tokens_used' => 0,
            'model_used' => 'demo-generator',
            'success' => true
        ];
    }
}
