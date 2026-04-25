<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\DB;

class JobFitScoringService
{
    /**
     * Calculate job fit score based on multiple factors
     *
     * @param string $userId
     * @param array $jobAnalysis
     * @param array $jobContext
     * @param string|null $jobPostedAt
     * @return array
     */
    public function calculateFitScore(
        string $userId,
        array $jobAnalysis,
        array $jobContext,
        ?string $jobPostedAt = null
    ): array {
        $breakdown = [
            'freshness' => $this->scoreFreshness($jobPostedAt),
            'competition' => $this->scoreCompetition($jobContext['proposals_count'] ?? null),
            'client_quality' => $this->scoreClientQuality($jobContext),
            'skill_match' => $this->scoreSkillMatch($userId, $jobAnalysis['skills'] ?? []),
            'budget_match' => $this->scoreBudgetMatch($userId, $jobContext['budget'] ?? null),
        ];

        $fitScore = array_sum($breakdown);
        
        return [
            'fit_score' => $fitScore,
            'recommendation' => $this->getRecommendation($fitScore),
            'apply' => $fitScore >= 40,
            'breakdown' => $breakdown,
            'warnings' => $this->generateWarnings($jobPostedAt, $jobContext),
            'tips' => $this->generateTips($jobContext),
        ];
    }

    /**
     * Score based on job freshness (max 25 points)
     */
    private function scoreFreshness(?string $jobPostedAt): int
    {
        if (!$jobPostedAt) {
            return 10; // Unknown, give benefit of doubt
        }

        try {
            $postedTime = new \DateTime($jobPostedAt);
            $now = new \DateTime();
            $hoursAgo = ($now->getTimestamp() - $postedTime->getTimestamp()) / 3600;

            if ($hoursAgo < 1) return 25;
            if ($hoursAgo < 3) return 20;
            if ($hoursAgo < 6) return 12;
            if ($hoursAgo < 24) return 5;
            return 0;
        } catch (\Exception $e) {
            return 10;
        }
    }

    /**
     * Score based on competition level (max 20 points)
     */
    private function scoreCompetition(?int $proposalsCount): int
    {
        if ($proposalsCount === null) {
            return 10; // Unknown
        }

        if ($proposalsCount <= 5) return 20;
        if ($proposalsCount <= 10) return 15;
        if ($proposalsCount <= 20) return 8;
        return 0;
    }

    /**
     * Score based on client quality (max 25 points)
     */
    private function scoreClientQuality(array $jobContext): int
    {
        $score = 0;

        // Client rating
        $rating = $jobContext['client_rating'] ?? null;
        if ($rating !== null) {
            if ($rating >= 4.5) $score += 10;
            elseif ($rating >= 3.5) $score += 5;
            elseif ($rating < 3) $score -= 5;
        }

        // Client spending
        $spending = $jobContext['client_spending'] ?? '';
        if ($spending) {
            if (preg_match('/\$?(\d+)([KM])/i', $spending, $matches)) {
                $amount = (int)$matches[1];
                $unit = strtoupper($matches[2]);
                
                if ($unit === 'M' || ($unit === 'K' && $amount >= 10)) {
                    $score += 10;
                } elseif ($unit === 'K' && $amount >= 1) {
                    $score += 5;
                }
            } elseif (preg_match('/\$?(\d+)/', $spending, $matches)) {
                $amount = (int)$matches[1];
                if ($amount < 100) $score -= 5;
            }
        }

        // Payment verified
        if (!empty($jobContext['has_payment_verified'])) {
            $score += 5;
        }

        return max(0, $score);
    }

    /**
     * Score based on skill match (max 20 points)
     */
    private function scoreSkillMatch(string $userId, array $jobSkills): int
    {
        if (empty($jobSkills)) {
            return 10; // Unknown
        }

        $userSkills = DB::table('user_skills')
            ->join('skills', 'user_skills.skill_id', '=', 'skills.id')
            ->where('user_skills.user_id', $userId)
            ->pluck('skills.name')
            ->map('strtolower')
            ->toArray();

        $matches = 0;
        foreach ($jobSkills as $skill) {
            if (in_array(strtolower($skill), $userSkills)) {
                $matches++;
            }
        }

        return min(20, $matches * 4);
    }

    /**
     * Score based on budget match (max 10 points)
     */
    private function scoreBudgetMatch(string $userId, ?string $budget): int
    {
        if (!$budget) {
            return 5; // Unknown
        }

        // Get user's hourly rate from profile
        $userProfile = DB::table('user_profiles')
            ->where('user_id', $userId)
            ->first();

        if (!$userProfile || !isset($userProfile->hourly_rate)) {
            return 5;
        }

        $userRate = (float)$userProfile->hourly_rate;

        // Extract budget amount
        if (preg_match('/\$?(\d+)/', $budget, $matches)) {
            $budgetAmount = (int)$matches[1];
            $difference = abs($budgetAmount - $userRate) / $userRate;

            if ($difference <= 0.2) return 10; // Within 20%
            if ($difference <= 0.5) return 5;  // Within 50%
            return 0;
        }

        return 5;
    }

    /**
     * Get recommendation based on score
     */
    private function getRecommendation(int $score): string
    {
        if ($score >= 80) return 'strong_match';
        if ($score >= 60) return 'good_match';
        if ($score >= 40) return 'weak_match';
        return 'skip';
    }

    /**
     * Generate warnings
     */
    private function generateWarnings(?string $jobPostedAt, array $jobContext): array
    {
        $warnings = [];

        if ($jobPostedAt) {
            try {
                $postedTime = new \DateTime($jobPostedAt);
                $now = new \DateTime();
                $hoursAgo = ($now->getTimestamp() - $postedTime->getTimestamp()) / 3600;

                if ($hoursAgo >= 5) {
                    $warnings[] = sprintf('Job is %.0f hours old - competition may be high', $hoursAgo);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        if (isset($jobContext['proposals_count']) && $jobContext['proposals_count'] > 15) {
            $warnings[] = 'High competition - ' . $jobContext['proposals_count'] . ' proposals already submitted';
        }

        if (isset($jobContext['client_rating']) && $jobContext['client_rating'] < 3.5) {
            $warnings[] = 'Low client rating - proceed with caution';
        }

        return $warnings;
    }

    /**
     * Generate actionable tips
     */
    private function generateTips(array $jobContext): array
    {
        $tips = [];

        $spending = $jobContext['client_spending'] ?? '';
        if ($spending && preg_match('/\$?(\d+)([KM])/i', $spending, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtoupper($matches[2]);
            
            if ($unit === 'M' || ($unit === 'K' && $amount >= 50)) {
                $tips[] = 'Client has $50K+ spend history - use peer-level tone';
            } elseif ($unit === 'K' && $amount >= 10) {
                $tips[] = 'Established client - show business understanding in proposal';
            }
        }

        if (isset($jobContext['client_rating']) && $jobContext['client_rating'] >= 4.5) {
            $tips[] = 'High-rated client - they know quality work when they see it';
        }

        return $tips;
    }
}
