<?php

namespace App\Http\Services;

use App\Http\Repositories\ProjectRepository;
use App\Http\Repositories\UserProfileRepository;

class ProjectMatchingService
{
    public function __construct(
        private ProjectRepository $projectRepo,
        private UserProfileRepository $userProfileRepo
    ) {}

    /**
     * Find relevant projects for a job or fallback to skills-only
     * 
     * @param string $userId
     * @param array $jobAnalysis - Output from JobAnalysisService
     * @return array
     */
    public function matchProjects(string $userId, array $jobAnalysis): array
    {
        $userProjects = $this->projectRepo->getUserProjects($userId);
        
        // If no projects, fallback to skills-only
        if ($userProjects->isEmpty()) {
            return $this->getSkillsFallback($userId);
        }

        // Score and rank projects
        $scoredProjects = $this->scoreProjects($userProjects, $jobAnalysis);
        
        // Return top 2-3 projects
        return array_slice($scoredProjects, 0, 3);
    }

    /**
     * Score projects based on relevance to job
     * Scoring Rules per v2 specs:
     * - Skill match: +10
     * - Integration match: +8
     * - Industry match: +5
     */
    private function scoreProjects($projects, array $jobAnalysis): array
    {
        $scoredProjects = [];
        
        foreach ($projects as $project) {
            $score = 0;
            $skillMatches = 0;
            $integrationMatches = 0;
            
            // Load project with relationships
            $project->load(['skills', 'integrations']);
            
            // Skills match (+10 per skill)
            $skillMatches = $this->countSkillMatches($project, $jobAnalysis['skills']);
            $score += $skillMatches * 10;
            
            // Integration match (+8 per integration)
            $integrationMatches = $this->countIntegrationMatches($project, $jobAnalysis['integrations'] ?? []);
            $score += $integrationMatches * 8;
            
            // Industry match (+5)
            if ($project->industry && 
                strtolower($project->industry) === strtolower($jobAnalysis['industry'])) {
                $score += 5;
            }
            
            $scoredProjects[] = [
                'project' => $project,
                'score' => $score,
                'skill_matches' => $skillMatches,
                'integration_matches' => $integrationMatches,
                'industry_match' => ($project->industry && strtolower($project->industry) === strtolower($jobAnalysis['industry']))
            ];
        }
        
        // Sort by score descending
        usort($scoredProjects, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $scoredProjects;
    }

    /**
     * Check if project is relevant to job type
     */
    private function isJobTypeRelevant($project, string $jobType): bool
    {
        $projectTitle = strtolower($project->title);
        $projectDesc = strtolower($project->description);
        
        $jobTypeKeywords = [
            'web development' => ['website', 'web', 'frontend', 'backend'],
            'mobile development' => ['mobile', 'app', 'android', 'ios'],
            'design' => ['design', 'ui', 'ux', 'logo', 'brand'],
            'content writing' => ['content', 'blog', 'article', 'writing'],
            'data analysis' => ['data', 'analytics', 'dashboard', 'report'],
            'digital marketing' => ['marketing', 'social', 'ads', 'campaign']
        ];
        
        $keywords = $jobTypeKeywords[$jobType] ?? [];
        
        foreach ($keywords as $keyword) {
            if (strpos($projectTitle . ' ' . $projectDesc, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Count skill matches between project and job requirements
     */
    private function countSkillMatches($project, array $jobSkills): int
    {
        // Get project skills
        $projectSkills = $project->skills ?? collect();
        $projectSkillNames = $projectSkills->pluck('name')->map('strtolower')->toArray();
        
        $matches = 0;
        foreach ($jobSkills as $skill) {
            if (in_array(strtolower($skill), $projectSkillNames)) {
                $matches++;
            }
        }
        
        return $matches;
    }

    /**
     * Count integration matches between project and job requirements
     */
    private function countIntegrationMatches($project, array $jobIntegrations): int
    {
        if (empty($jobIntegrations)) {
            return 0;
        }

        // Get project integrations
        $projectIntegrations = $project->integrations ?? collect();
        $projectIntegrationNames = $projectIntegrations->pluck('integration_name')->map('strtolower')->toArray();
        
        $matches = 0;
        foreach ($jobIntegrations as $integration) {
            if (in_array(strtolower($integration), $projectIntegrationNames)) {
                $matches++;
            }
        }
        
        return $matches;
    }

    /**
     * Fallback when user has no projects - return skills only
     */
    private function getSkillsFallback(string $userId): array
    {
        $userProfile = $this->userProfileRepo->find($userId);
        
        return [
            'type' => 'skills_only',
            'profile' => $userProfile,
            'message' => 'No projects available, using skills-based proposal'
        ];
    }
}
