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

            if (!$aiResponse['success']) {
                $errorMsg = $aiResponse['error'] ?? 'Unknown error';
                Log::error('AI provider failed', [
                    'provider' => $provider,
                    'error' => $errorMsg,
                    'response' => $aiResponse
                ]);
                throw new \Exception('Failed to generate proposal: ' . $errorMsg);
            }

            // 6.1. Parse structured AI response
            try {
                $parsedResponse = $this->parseAiResponse($aiResponse['content']);
            } catch (\Exception $e) {
                Log::error('Failed to parse AI response', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                    'raw_content' => substr($aiResponse['content'] ?? '', 500)
                ]);
                throw new \Exception('Failed to parse AI response: ' . $e->getMessage());
            }

            // 6.2. Handle critical risk case
            if (!$parsedResponse['should_send']) {
                $this->logUsage($userId, 'proposal_generation');

                return [
                    'proposal_generated' => false,
                    'risk_assessment' => [
                        'risk_level' => $parsedResponse['risk_level'],
                        'reasoning' => $parsedResponse['notes']
                    ],
                    'apply_recommendation' => [
                        'should_apply' => false,
                        'message' => 'Job flagged as critical risk by AI analysis. Proposal generation blocked.'
                    ],
                    'job_analysis' => $jobAnalysis,
                    'matched_projects' => $matchedData,
                    'proposal' => null,
                    'tokens_used' => $aiResponse['tokens_used'],
                    'provider_used' => $aiResponse['model_used']
                ];
            }

            // 6.3. Calculate job fit score
            $fitScore = $this->jobFitScoringService->calculateFitScore(
                $userId,
                $jobAnalysis,
                $jobContext,
                $jobContext['job_posted_at'] ?? null
            );

            // 6.4. Validate proposal quality
            $quality = $this->proposalQualityService->validate(
                $parsedResponse['proposal_text'],
                $jobDescription
            );

            // 6.5. Retry if quality is poor
            if (!$quality['passed'] && $quality['regenerate']) {
                Log::warning('Proposal quality check failed, retrying', [
                    'failed_checks' => $quality['failed_checks'],
                    'score' => $quality['score']
                ]);

                $retryPrompt = $this->buildRetryPrompt($prompt, $quality['failed_checks'], $parsedResponse['proposal_text']);
                $retryResponse = $this->generateWithProvider($retryPrompt, $provider);
                
                if (!$retryResponse['success']) {
                    throw new \Exception('Failed to generate proposal retry: ' . $retryResponse['error']);
                }
                
                $parsedResponse = $this->parseAiResponse($retryResponse['content']);
                $quality = $this->proposalQualityService->validate($parsedResponse['proposal_text'], $jobDescription);
            }
            
            // 7. Save proposal
            $proposal = $this->storeProposal($proposalRequest->id, $parsedResponse, $quality['score']);
            
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
        $lastError = null;
        
        foreach ($priority as $name) {
            if (!isset($providers[$name])) continue;
            [$service, $configKey] = $providers[$name];
            
            if (!config($configKey)) {
                Log::info("Skipping {$name} provider - API key not configured");
                continue;
            }
            
            try {
                $result = $service->generateProposal($prompt);
                
                if ($result['success']) {
                    Log::info("Successfully generated proposal using {$name}", [
                        'tokens_used' => $result['tokens_used'],
                        'model' => $result['model_used']
                    ]);
                    return $result;
                } else {
                    $lastError = $result['error'] ?? 'Unknown error';
                    Log::warning("{$name} provider failed", [
                        'error' => $lastError,
                        'will_retry' => true
                    ]);
                    continue;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception in {$name} provider", [
                    'error' => $lastError,
                    'will_retry' => true
                ]);
                continue;
            }
        }
        
        Log::warning('All AI providers failed, falling back to demo proposal', [
            'last_error' => $lastError,
            'attempted_providers' => $priority
        ]);
        
        return $this->generateDemoProposal();
    }

    /**
     * Validate user usage limits
     */
    private function validateUsageLimits(string $userId): void
    {
        $dailyLimit = (int) config('ai.generation.daily_limit', 10);

        // Set AI_DAILY_LIMIT=0 (or negative) to disable limit in local/testing.
        if ($dailyLimit <= 0) {
            return;
        }

        $todayUsage = UsageLog::where('user_id', $userId)
            ->where('request_type', 'proposal_generation')
            ->whereDate('date', today())
            ->sum('count');

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

    /**
     * AI-driven fake job detection and authenticity assessment
     */
    private function assessJobAuthenticity(string $jobDescription, array $jobContext): array
    {
        $prompt = $this->buildFakeJobDetectionPrompt($jobDescription, $jobContext);
        
        $aiResponse = $this->generateWithProvider($prompt, null);
        
        if (!$aiResponse['success']) {
            Log::warning('AI fake job detection failed, using fallback', [
                'error' => $aiResponse['error'] ?? 'Unknown error'
            ]);
            return $this->fallbackAuthenticityCheck($jobDescription, $jobContext);
        }
        
        return $this->parseAuthenticityResponse($aiResponse['content']);
    }

    /**
     * Build AI prompt for fake job detection
     */
    private function buildFakeJobDetectionPrompt(string $jobDescription, array $jobContext): string
    {
        $contextInfo = json_encode($jobContext, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
You are an expert at detecting fake/scam jobs on freelancing platforms. Analyze this job posting and determine if it's legitimate or risky.

JOB DESCRIPTION:
{$jobDescription}

CLIENT CONTEXT:
{$contextInfo}

Analyze for red flags:
- Suspicious payment terms (upfront payment, security deposits, commission-only)
- Off-platform communication requests (Telegram, WhatsApp, personal email)
- Vague job descriptions with unrealistic promises
- Brand-new clients with no history asking for free trials
- Grammar/spelling that suggests scam operations
- Budget mismatches (enterprise work for \$5)

Return ONLY valid JSON (no markdown):
{
  "risk_level": "low|medium|high",
  "should_apply": true|false,
  "confidence": 55-95,
  "score": -10 to +10,
  "reasoning": "concise explanation of red flags or green flags"
}
PROMPT;
    }

    /**
     * Parse AI authenticity response
     */
    private function parseAuthenticityResponse(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);
        
        // Try to fix truncated JSON by completing the structure
        if (substr($content, -1) !== '}' && substr($content, -1) !== ']') {
            Log::warning('Detected truncated JSON, attempting to fix', [
                'content_end' => substr($content, -50),
                'content_length' => strlen($content)
            ]);
            
            // Try to close the JSON structure
            $openBraces = substr_count($content, '{') - substr_count($content, '}');
            $openBrackets = substr_count($content, '[') - substr_count($content, ']');
            
            for ($i = 0; $i < $openBraces; $i++) {
                $content .= '}';
            }
            for ($i = 0; $i < $openBrackets; $i++) {
                $content .= ']';
            }
        }
        
        $decoded = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return [
                'risk_level' => $decoded['risk_level'] ?? 'medium',
                'should_apply' => $decoded['should_apply'] ?? true,
                'confidence' => $decoded['confidence'] ?? 70,
                'score' => $decoded['score'] ?? 0,
                'reasoning' => $decoded['reasoning'] ?? 'AI analysis completed',
            ];
        }
        
        Log::warning('Failed to parse AI authenticity response', [
            'content' => substr($content, 0, 200),
            'json_error' => json_last_error_msg(),
            'json_error_code' => json_last_error(),
            'attempted_fix' => true
        ]);
        
        return [
            'risk_level' => 'medium',
            'should_apply' => true,
            'confidence' => 60,
            'score' => 0,
            'reasoning' => 'Could not parse AI response',
        ];
    }

    /**
     * Fallback algorithmic check if AI fails
     */
    private function fallbackAuthenticityCheck(string $jobDescription, array $jobContext): array
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
        }

        $description = strtolower($jobDescription);
        $suspiciousSignals = ['telegram', 'whatsapp', 'pay upfront', 'security deposit', 'outside upwork', 'free sample'];
        foreach ($suspiciousSignals as $signal) {
            if (str_contains($description, $signal)) {
                $score -= 3;
                $reasons[] = "Suspicious signal: {$signal}";
            }
        }

        $riskLevel = $score <= -2 ? 'high' : ($score >= 3 ? 'low' : 'medium');
        $shouldApply = $score > -2;
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
            'content' => $aiResponse['proposal_text'] ?? $aiResponse['content'] ?? '',
            'tokens_used' => $aiResponse['tokens_used'] ?? 0,
            'model_used' => $aiResponse['model_used'] ?? 'unknown',
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
     * Parse structured JSON response from AI
     */
    private function parseAiResponse(string $rawResponse): array
    {
        // Try to extract JSON from response with headers and formatting
        $jsonStart = strpos($rawResponse, '{');
        $jsonEnd = strrpos($rawResponse, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
            $clean = substr($rawResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
        } else {
            // Fallback to stripping markdown code fences
            $clean = preg_replace('/^```json\s*/i', '', trim($rawResponse));
            $clean = preg_replace('/\s*```$/', '', $clean);
        }

        // Try to fix truncated JSON by completing structure
        if (substr($clean, -1) !== '}' && substr($clean, -1) !== ']') {
            $openBraces = substr_count($clean, '{') - substr_count($clean, '}');
            $openBrackets = substr_count($clean, '[') - substr_count($clean, ']');
            
            for ($i = 0; $i < $openBraces; $i++) {
                $clean .= '}';
            }
            for ($i = 0; $i < $openBrackets; $i++) {
                $clean .= ']';
            }
        }

        // Log the raw response for debugging
        Log::info('AI Raw Response', [
            'raw_length' => strlen($rawResponse),
            'clean_length' => strlen($clean),
            'raw_preview' => substr($rawResponse, 0, 200),
            'clean_preview' => substr($clean, 0, 200),
        ]);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('AI response JSON parse failed', [
                'raw'   => substr($rawResponse, 0, 500),
                'clean' => substr($clean, 0, 500),
                'error' => json_last_error_msg(),
                'error_code' => json_last_error(),
            ]);
            
            // If JSON parsing fails, try to extract proposal text from the response
            $proposalText = $this->extractProposalFromText($rawResponse);
            
            if (!empty($proposalText)) {
                Log::warning('Attempting to extract proposal from non-JSON response');
                return [
                    'should_send' => true,
                    'risk_level' => 'MEDIUM',
                    'fake_signals' => [],
                    'green_flags' => [],
                    'pain_point' => '',
                    'job_category' => '',
                    'fit_score' => 70,
                    'strongest_proof' => 'AI generated content',
                    'honest_gap' => 'Unable to parse structured response',
                    'proposal_text' => $proposalText,
                    'word_count' => str_word_count($proposalText),
                    'hook' => '',
                    'question' => '',
                    'confidence' => 'LOW',
                    'notes' => 'Response was not valid JSON, extracted text instead',
                ];
            }
            
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

    /**
     * Extract proposal text from non-JSON responses
     */
    private function extractProposalFromText(string $text): string
    {
        // Look for proposal section in structured responses
        $proposalPatterns = [
            '/"proposal":\s*\{[^}]*"text":\s*"([^"]+)"/i',
            '/"text":\s*"([^"]+)"/i',
            '/PROPOSAL\s*\n\s*([^\n]+)/i',
            '/proposal[^:]*:\s*([^\n]+)/i',
        ];
        
        foreach ($proposalPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $proposal = trim($matches[1]);
                if (!empty($proposal) && strlen($proposal) > 20) {
                    return stripslashes($proposal);
                }
            }
        }
        
        // If no specific proposal found, try to extract meaningful text
        // Remove headers and JSON-like content
        $clean = preg_replace('/^===.*===\s*/m', '', $text);
        $clean = preg_replace('/^\s*"[^"]*":\s*/m', '', $clean);
        $clean = preg_replace('/^\s*\{[^}]*\}\s*/m', '', $clean);
        $clean = preg_replace('/^\s*\[[^\]]*\]\s*/m', '', $clean);
        
        // Split into sentences and take the most meaningful ones
        $sentences = preg_split('/[.!?]+/', $clean);
        $sentences = array_filter($sentences, function($sentence) {
            $sentence = trim($sentence);
            return strlen($sentence) > 15 && 
                   !preg_match('/^(risk|fake|signal|green|flag|assessment|analysis)/i', $sentence) &&
                   !preg_match('/^\s*[{}[\]]/', $sentence);
        });
        
        if (!empty($sentences)) {
            return implode('. ', array_slice($sentences, 0, 3)) . '.';
        }
        
        return '';
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
