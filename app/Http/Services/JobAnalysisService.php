<?php

namespace App\Http\Services;

class JobAnalysisService
{
    /**
     * Analyze job description and extract relevant information
     * 
     * @param string $jobDescription
     * @return array
     */
    public function analyze(string $jobDescription): array
    {
        // Extract job type based on keywords
        $jobType = $this->detectJobType($jobDescription);
        
        // Extract required skills
        $skills = $this->extractSkills($jobDescription);
        
        // Extract integrations/tools
        $integrations = $this->extractIntegrations($jobDescription);
        
        // Extract industry
        $industry = $this->detectIndustry($jobDescription);
        
        // Extract pain point
        $painPoint = $this->extractPainPoint($jobDescription);
        
        return [
            'job_type' => $jobType,
            'skills' => $skills,
            'integrations' => $integrations,
            'industry' => $industry,
            'pain_point' => $painPoint,
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
