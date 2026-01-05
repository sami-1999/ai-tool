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
        
        // Extract industry
        $industry = $this->detectIndustry($jobDescription);
        
        return [
            'job_type' => $jobType,
            'skills' => $skills,
            'industry' => $industry,
            'description' => $jobDescription
        ];
    }

    /**
     * Detect job type from description
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

        foreach ($jobTypeKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return 'general';
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
}
