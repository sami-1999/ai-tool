<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;

class JobAnalysisService
{
    public function __construct(
        private GroqService $groqService,
        private GeminiService $geminiService,
        private ClaudeService $claudeService,
        private OpenAIService $openAIService
    ) {}

    /**
     * Analyze job description using AI (fully AI-driven approach)
     * 
     * @param string $jobDescription
     * @return array
     */
    public function analyze(string $jobDescription): array
    {
        $prompt = $this->buildJobAnalysisPrompt($jobDescription);
        
        $aiResponse = $this->callAIProvider($prompt);
        
        if (!$aiResponse['success']) {
            Log::warning('AI job analysis failed, using fallback', [
                'error' => $aiResponse['error'] ?? 'Unknown error'
            ]);
            return $this->fallbackAnalysis($jobDescription);
        }
        
        $analysis = $this->parseAIAnalysis($aiResponse['content']);
        
        return [
            'job_type' => $analysis['job_type'] ?? 'general',
            'skills' => $analysis['skills'] ?? [],
            'integrations' => $analysis['integrations'] ?? [],
            'industry' => $analysis['industry'] ?? 'general',
            'pain_point' => $analysis['pain_point'] ?? '',
            'description' => $jobDescription
        ];
    }

    /**
     * Build AI prompt for comprehensive job analysis
     */
    private function buildJobAnalysisPrompt(string $jobDescription): string
    {
        return <<<PROMPT
Analyze this job posting and extract structured data. Return ONLY valid JSON, no markdown formatting.

JOB DESCRIPTION:
{$jobDescription}

Extract:
1. job_type: categorize as one of: web development, mobile development, design, content writing, data analysis, digital marketing, devops, ai/ml, blockchain, general
2. skills: array of technical skills mentioned (e.g., ["PHP", "Laravel", "React"])
3. integrations: array of tools/platforms mentioned (e.g., ["Stripe", "AWS", "Firebase"])
4. industry: categorize as: healthcare, fintech, ecommerce, education, real estate, saas, entertainment, general
5. pain_point: extract the client's core problem in 1-2 sentences (what they're struggling with)

Return JSON format:
{
  "job_type": "string",
  "skills": ["skill1", "skill2"],
  "integrations": ["tool1", "tool2"],
  "industry": "string",
  "pain_point": "string"
}
PROMPT;
    }

    /**
     * Call AI provider with fallback chain
     */
    private function callAIProvider(string $prompt): array
    {
        $providers = [
            ['service' => $this->groqService, 'config' => 'services.groq.api_key'],
            ['service' => $this->geminiService, 'config' => 'services.gemini.api_key'],
            ['service' => $this->claudeService, 'config' => 'services.claude.api_key'],
            ['service' => $this->openAIService, 'config' => 'services.openai.api_key'],
        ];

        foreach ($providers as $provider) {
            if (config($provider['config'])) {
                try {
                    $response = $provider['service']->generateProposal($prompt);
                    if ($response['success']) {
                        return $response;
                    }
                } catch (\Exception $e) {
                    Log::warning('AI provider failed in job analysis', [
                        'provider' => get_class($provider['service']),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        }

        return ['success' => false, 'error' => 'All AI providers failed'];
    }

    /**
     * Parse AI response and extract structured data
     */
    private function parseAIAnalysis(string $content): array
    {
        // Try to extract JSON from response
        $content = trim($content);
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);
        
        $decoded = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        Log::warning('Failed to parse AI job analysis response', [
            'content' => substr($content, 0, 200)
        ]);
        
        return [];
    }

    /**
     * Fallback to keyword-based analysis if AI fails
     */
    private function fallbackAnalysis(string $jobDescription): array
    {
        return [
            'job_type' => $this->detectJobType($jobDescription),
            'skills' => $this->extractSkills($jobDescription),
            'integrations' => $this->extractIntegrations($jobDescription),
            'industry' => $this->detectIndustry($jobDescription),
            'pain_point' => $this->extractPainPoint($jobDescription),
            'description' => $jobDescription
        ];
    }

    /**
     * Detect job type from description using keyword scoring
     */
    private function detectJobType(string $description): string
    {
        $description = strtolower($description);
        
        $jobTypeKeywords = [
            'web development' => ['website', 'web app', 'frontend', 'backend', 'fullstack'],
            'mobile development' => ['mobile app', 'android', 'ios', 'react native', 'flutter'],
            'design' => ['design', 'ui', 'ux', 'graphic', 'logo', 'branding'],
            'content writing' => ['content', 'blog', 'article', 'copywriting', 'seo'],
            'data analysis' => ['data', 'analysis', 'dashboard', 'excel', 'sql'],
            'digital marketing' => ['marketing', 'social media', 'ads', 'campaign']
        ];

        $scores = [];
        
        // Score ALL job types by keyword count
        foreach ($jobTypeKeywords as $type => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    $score++;
                }
            }
            $scores[$type] = $score;
        }
        
        // Return the highest scoring type
        arsort($scores);
        $topType = array_key_first($scores);
        
        return ($scores[$topType] > 0) ? $topType : 'general';
    }

    /**
     * Extract skills from job description
     */
    private function extractSkills(string $description): array
    {
        $description = strtolower($description);
        
        // Common skills to look for
        $skillKeywords = [
            'php', 'laravel', 'javascript', 'react', 'vue', 'node',
            'python', 'django', 'mysql', 'postgresql', 'mongodb',
            'html', 'css', 'bootstrap', 'tailwind', 'figma',
            'photoshop', 'wordpress', 'shopify', 'api', 'rest',
            'git', 'aws', 'docker', 'seo', 'content writing'
        ];

        $foundSkills = [];
        foreach ($skillKeywords as $skill) {
            if (strpos($description, $skill) !== false) {
                $foundSkills[] = $skill;
            }
        }

        return $foundSkills;
    }

    /**
     * Extract integrations/tools from job description
     */
    private function extractIntegrations(string $description): array
    {
        $description = strtolower($description);
        
        // Common integrations/tools to look for
        $integrationKeywords = [
            'stripe', 'paypal', 'firebase', 'aws', 'google analytics',
            'mailchimp', 'sendgrid', 'twilio', 'slack', 'zoom',
            'shopify', 'woocommerce', 'magento', 'salesforce',
            'hubspot', 'zapier', 'webhooks', 'oauth', 'jwt',
            'redis', 'elasticsearch', 'docker', 'kubernetes'
        ];

        $foundIntegrations = [];
        foreach ($integrationKeywords as $integration) {
            if (strpos($description, $integration) !== false) {
                $foundIntegrations[] = $integration;
            }
        }

        return $foundIntegrations;
    }

    /**
     * Detect industry from job description
     */
    private function detectIndustry(string $description): string
    {
        $description = strtolower($description);
        
        $industryKeywords = [
            'healthcare' => ['health', 'medical', 'hospital', 'clinic'],
            'fintech' => ['finance', 'banking', 'payment', 'fintech'],
            'ecommerce' => ['ecommerce', 'shop', 'store', 'retail'],
            'education' => ['education', 'learning', 'course', 'school'],
            'real estate' => ['real estate', 'property', 'rental'],
            'saas' => ['saas', 'software', 'platform', 'dashboard']
        ];

        foreach ($industryKeywords as $industry => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    return $industry;
                }
            }
        }

        return 'general';
    }

    /**
     * Extract pain point from job description
     * 
     * @param string $description
     * @return string
     */
    private function extractPainPoint(string $description): string
    {
        $problemSignals = [
            'struggling', 'need help', 'having trouble', 'broken', 'failing',
            'not working', 'can\'t', 'cannot', 'slow', 'outdated', 'migration',
            'rebuild', 'fix', 'urgent', 'asap', 'deadline', 'behind'
        ];
        
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $description, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($sentences as $sentence) {
            $lowerSentence = strtolower($sentence);
            foreach ($problemSignals as $signal) {
                if (strpos($lowerSentence, $signal) !== false) {
                    // Return first matching sentence (max 150 chars)
                    return substr(trim($sentence), 0, 150);
                }
            }
        }
        
        return '';
    }
}
