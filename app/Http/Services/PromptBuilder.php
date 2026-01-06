<?php

namespace App\Http\Services;

use App\Models\SuccessfulProposalPattern;
use Illuminate\Support\Facades\DB;

class PromptBuilder
{
    /**
     * Build ethical prompt according to v2 specifications
     */
    public function buildEthicalPrompt(
        array $userProfile,
        array $jobAnalysis,
        array $matchedData,
        string $jobDescription,
        string $userId
    ): string {
        $prompt = "Generate a personalized Upwork proposal based on the following:\n\n";
        
        // Freelancer Background Section
        $prompt .= $this->buildFreelancerBackground($userProfile, $matchedData);
        
        // Load successful proposal patterns for learning
        $successfulPatterns = $this->getSuccessfulProposalPatterns($userId, $jobAnalysis['job_type']);
        if (!empty($successfulPatterns)) {
            $prompt .= $this->buildSuccessfulPatternsSection($successfulPatterns);
        }
        
        // Rules Section (v2 specifications)
        $prompt .= $this->buildRulesSection();
        
        // Job Description Section
        $prompt .= $this->buildJobDescriptionSection($jobDescription);
        
        return $prompt;
    }

    /**
     * Build freelancer background section
     */
    private function buildFreelancerBackground(array $userProfile, array $matchedData): string
    {
        $section = "FREELANCER BACKGROUND:\n";
        $section .= "- Title: " . ($userProfile['title'] ?? 'Freelancer') . "\n";
        $section .= "- Experience: " . ($userProfile['years_experience'] ?? '2') . " years\n";
        
        if (isset($matchedData['type']) && $matchedData['type'] === 'skills_only') {
            $section .= $this->buildSkillsOnlySection($userProfile, $matchedData);
        } else {
            $section .= $this->buildProjectBasedSection($matchedData);
        }
        
        return $section . "\n";
    }

    /**
     * Build skills-only section for users without projects
     */
    private function buildSkillsOnlySection(array $userProfile, array $matchedData): string
    {
        $section = "- Approach: Skills-based (honest about learning journey)\n";
        $section .= "- Skills: " . $this->formatUserSkills($userProfile['skills'] ?? []) . "\n";
        $section .= "- Writing Style: " . ($userProfile['writing_style_notes'] ?? 'Professional and direct') . "\n";
        
        return $section;
    }

    /**
     * Build project-based section for users with projects
     */
    private function buildProjectBasedSection(array $matchedData): string
    {
        $section = "\nRELEVANT PROJECTS:\n";
        
        foreach (array_slice($matchedData, 0, 3) as $index => $scoredProject) {
            $project = $scoredProject['project'];
            $section .= ($index + 1) . ". " . $project->title . "\n";
            $section .= "   Description: " . substr($project->description, 0, 150) . "...\n";
            
            if (!empty($project->challenges)) {
                $section .= "   Challenge solved: " . substr($project->challenges, 0, 100) . "...\n";
            }
            
            if (!empty($project->outcome)) {
                $section .= "   Outcome: " . substr($project->outcome, 0, 100) . "...\n";
            }
            
            // Include integrations if they match
            if (!empty($project->integration_names)) {
                $section .= "   Integrations used: " . implode(', ', $project->integration_names) . "\n";
            }
            
            $section .= "\n";
        }
        
        return $section;
    }

    /**
     * Build successful patterns section for controlled learning
     */
    private function buildSuccessfulPatternsSection(array $successfulPatterns): string
    {
        $section = "SUCCESSFUL PROPOSAL PATTERNS (for tone/structure reference only):\n";
        
        foreach ($successfulPatterns as $pattern) {
            if (!empty($pattern->tone)) {
                $section .= "- Tone that worked: " . $pattern->tone . "\n";
            }
            if (!empty($pattern->structure_notes)) {
                $section .= "- Structure notes: " . $pattern->structure_notes . "\n";
            }
        }
        
        return $section . "\n";
    }

    /**
     * Build rules section according to v2 specs
     */
    private function buildRulesSection(): string
    {
        return "RULES (STRICT COMPLIANCE REQUIRED):\n" .
               "- Maximum 120 words\n" .
               "- Human, conversational tone\n" .
               "- No buzzwords or clichés ('passionate', 'guru', 'expert')\n" .
               "- No fake experience claims\n" .
               "- Mention self-learning honestly if relevant\n" .
               "- Ask 1 smart, relevant question\n" .
               "- Reference specific project experience when available\n" .
               "- Show genuine understanding of client's needs\n" .
               "- Avoid generic proposals\n\n";
    }

    /**
     * Build job description section
     */
    private function buildJobDescriptionSection(string $jobDescription): string
    {
        return "JOB DESCRIPTION:\n" . 
               $jobDescription . "\n\n" .
               "Generate a compelling, honest, and personalized proposal:";
    }

    /**
     * Format user skills for the prompt
     */
    private function formatUserSkills(array $skills): string
    {
        if (empty($skills)) {
            return 'Various technical skills';
        }

        $skillNames = array_map(function ($skill) {
            return $skill['name'] . ' (' . ($skill['proficiency_level'] ?? 'intermediate') . ')';
        }, $skills);

        return implode(', ', array_slice($skillNames, 0, 6)); // Limit to 6 skills
    }

    /**
     * Get successful proposal patterns for controlled learning
     */
    private function getSuccessfulProposalPatterns(string $userId, string $jobType): array
    {
        return SuccessfulProposalPattern::where('user_id', $userId)
            ->where('job_type', $jobType)
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get()
            ->toArray();
    }
}
