<?php

namespace App\Http\Services;

use App\Models\SuccessfulProposalPattern;
use Illuminate\Support\Facades\Log;

class PromptBuilder
{
    private const MAX_PROJECTS     = 3;
    private const MAX_SKILLS       = 4;
    private const MAX_PATTERNS     = 2;
    private const DESCRIPTION_TRIM = 150;
    private const CHALLENGE_TRIM   = 100;
    private const OUTCOME_TRIM     = 120;
    private const JOB_DESC_TRIM    = 2000;

    private const FORBIDDEN_PHRASES = [
        'I am excited', 'I\'m excited', 'excited to',
        'passionate', 'guru', 'expert', 'leverage',
        'I would be delighted', 'I am confident I can deliver',
        'It would be my pleasure', 'I am confident',
        'hard-working', 'hardworking', 'team player',
        'detail-oriented', 'self-motivated', 'go-getter',
        'results-driven', 'dynamic', 'synergy', 'proactive',
        'I think I\'m the perfect', 'perfect fit', 'perfect candidate',
        'I assure you', 'rest assured', 'without a doubt',
        'I guarantee', 'best regards', 'dear client',
    ];

    /**
     * Build ethical prompt according to v2 specifications
     */
    public function buildEthicalPrompt(
        array  $userProfile,
        array  $jobAnalysis,
        array  $matchedData,
        string $jobDescription,
        string $userId,
        array  $jobContext    = [],
        array  $riskAssessment = []
    ): string {

        if (empty(trim($jobDescription))) {
            throw new \InvalidArgumentException('jobDescription cannot be empty');
        }
        if (empty(trim($userId))) {
            throw new \InvalidArgumentException('userId cannot be empty');
        }

        $promptTemplate = file_get_contents(
        resource_path('prompts/master_proposal_prompt.txt')
    );

    if ($promptTemplate === false) {
        throw new \RuntimeException('Master prompt file not found at resources/prompts/master_proposal_prompt.txt');
    }

    $freelancerBackground = $this->buildFreelancerBackground($userProfile, $matchedData);

    $successfulPatterns = $this->getSuccessfulProposalPatterns(
        $userId,
        $jobAnalysis['job_type'] ?? 'general'
    );

    $patternsBlock = !empty($successfulPatterns)
        ? $this->buildSuccessfulPatternsSection($successfulPatterns)
        : '';

    $clientContextBlock = $this->buildClientContextSection($jobContext, $riskAssessment);

    $finalPrompt = str_replace(
        [
            '{{JOB_DESCRIPTION}}',
            '{{FREELANCER_BACKGROUND}}',
            '{{CLIENT_CONTEXT}}',
            '{{SUCCESSFUL_PATTERNS}}',
        ],
        [
            substr(trim($jobDescription), 0, self::JOB_DESC_TRIM),
            $freelancerBackground,
            $clientContextBlock,
            $patternsBlock,
        ],
        $promptTemplate
    );

    return $finalPrompt;
    }

    private function buildClientContextSection(array $jobContext, array $riskAssessment): string
    {
        if (empty($jobContext) && empty($riskAssessment)) {
            return '';
        }

        $section = "CLIENT & JOB CONTEXT:\n";

        if (!empty($jobContext['client_name'])) {
            $section .= "- Client Name: {$jobContext['client_name']}\n";
        }
        if (isset($jobContext['client_rating']) && $jobContext['client_rating'] !== null) {
            $section .= "- Client Rating: {$jobContext['client_rating']}/5\n";
        }
        if (!empty($jobContext['client_spending'])) {
            $section .= "- Client Spending: {$jobContext['client_spending']}\n";
            $section .= "- Client Tier: " . $this->resolveClientTier($jobContext['client_spending']) . "\n";
        }
        if (!empty($jobContext['job_type'])) {
            $section .= "- Job Type: {$jobContext['job_type']}\n";
        }
        if (!empty($jobContext['budget'])) {
            $section .= "- Budget: {$jobContext['budget']}\n";
        }

        if (!empty($riskAssessment)) {
            $riskLevel = strtoupper($riskAssessment['risk_level'] ?? 'MEDIUM');
            $section  .= "- Job Authenticity Risk: {$riskLevel}\n";

            if (!empty($riskAssessment['reasoning'])) {
                $section .= "- Risk Notes: {$riskAssessment['reasoning']}\n";
            }

            $applyLabel = ($riskAssessment['should_apply'] ?? false)
                ? 'APPLY'
                : 'DO NOT APPLY (unless user forces generation)';
            $section .= "- Apply Recommendation: {$applyLabel}\n";
        }

        return $section . "\n";
    }

    /**
     * Build freelancer background section
     */
    private function buildFreelancerBackground(array $userProfile, array $matchedData): string
    {
        $section  = "FREELANCER BACKGROUND:\n";
        $section .= "- Title: " . ($userProfile['title'] ?? 'Freelancer') . "\n";
        $section .= "- Experience: " . ($userProfile['years_experience'] ?? '2') . " years\n";

        if (isset($matchedData['type']) && $matchedData['type'] === 'skills_only') {
            $section .= $this->buildSkillsOnlySection($userProfile);
        } else {
            $section .= $this->buildProjectBasedSection($matchedData);
        }

        return $section . "\n";
    }

    /**
     * Build skills-only section for users without projects
     */
    private function buildSkillsOnlySection(array $userProfile): string
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
        $count   = 0;

        foreach ($matchedData as $scoredProject) {
            if ($count >= self::MAX_PROJECTS) {
                break;
            }

            if (!isset($scoredProject['project'])) {
                Log::warning('PromptBuilder: matched project entry missing [project] key', [
                    'keys' => array_keys($scoredProject),
                ]);
                continue;
            }

            $project  = $scoredProject['project'];
            $index    = $count + 1;
            $section .= "{$index}. " . ($project->title ?? 'Untitled Project') . "\n";
            $section .= "   Description: " . substr($project->description ?? '', 0, self::DESCRIPTION_TRIM) . "...\n";

            if (!empty($project->challenges)) {
                $section .= "   Challenge solved: " . substr($project->challenges, 0, self::CHALLENGE_TRIM) . "...\n";
            }
            if (!empty($project->outcome)) {
                $section .= "   Outcome: " . substr($project->outcome, 0, self::OUTCOME_TRIM) . "...\n";
            }
            if (!empty($project->integration_names) && is_array($project->integration_names)) {
                $section .= "   Integrations used: " . implode(', ', $project->integration_names) . "\n";
            }

            $section .= "\n";
            $count++;
        }

        return $section;
    }

    /**
     * Build successful patterns section for controlled learning
     */
    private function buildSuccessfulPatternsSection(array $successfulPatterns): string
    {
        $section = "SUCCESSFUL PROPOSAL PATTERNS (tone/structure reference only — do NOT copy):\n";

        foreach ($successfulPatterns as $pattern) {
            if (!empty($pattern['hook_opening_line'])) {
                $described = $this->describeHookStyle($pattern['hook_opening_line']);
                $section  .= "- Opening style that worked: {$described}\n";
            }
            if (!empty($pattern['tone'])) {
                $section .= "- Tone that worked: " . $pattern['tone'] . "\n";
            }
            if (!empty($pattern['structure_notes'])) {
                $section .= "- Structure notes: " . $pattern['structure_notes'] . "\n";
            }
        }

        return $section . "\n";
    }

    /**
     * Build rules section according to v2 specs with job-winning techniques
     */
    private function buildRulesSection(string $painPoint = '', array $jobContext = []): string
    {
        $spending   = $jobContext['client_spending'] ?? '';
        $clientTier = $spending
            ? $this->resolveClientTier($spending)
            : ($jobContext['client_tier_resolved'] ?? 'standard');
        $toneGuidance = $this->resolveToneGuidance($clientTier);
        $forbiddenStr = implode(', ', array_map(fn($p) => "\"{$p}\"", self::FORBIDDEN_PHRASES));

        $painPointLine = $painPoint
            ? "- The client's core pain point is: \"{$painPoint}\" — your FIRST sentence must address this directly\n"
            : "- Your FIRST sentence must reference something SPECIFIC from the job description above\n";

        return
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "RULES — READ EVERY LINE BEFORE WRITING:\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .

            "HOOK (your first sentence — the only one that matters):\n" .
            $painPointLine .
            "- Do NOT start with 'I', 'Hi', 'Hello', 'My name', or 'I saw your job'\n" .
            "- Start mid-thought, as if you already know their problem\n\n" .

            "STRUCTURE (4 parts, all in prose — NO bullet points ever):\n" .
            "1. Hook [10-15 words]: Prove you READ and UNDERSTOOD their specific problem\n" .
            "2. Credibility [25-35 words]: ONE similar past project + ONE measurable result (%, \$, time)\n" .
            "3. Approach [35-45 words]: HOW you'd solve it — be specific, not generic\n" .
            "4. Question [10-15 words]: ONE smart question about their goal, not their tech stack\n\n" .

            "TONE:\n" .
            "- {$toneGuidance}\n" .
            "- Use contractions: I've, I'd, you're, it's, that's\n" .
            "- Mix short punchy sentences with longer ones\n" .
            "- Casual transitions: Actually, In fact, So, And, But\n\n" .

            "FORBIDDEN — automatic disqualification if any appear:\n" .
            "- These exact phrases: {$forbiddenStr}\n" .
            "- Any bullet point or numbered list in the output\n" .
            "- Fake projects not in the RELEVANT PROJECTS section above\n" .
            "- Copying the hook from SUCCESSFUL PATTERNS verbatim\n\n" .

            "WORD BUDGET: 100-120 words exactly. Not 99. Not 121.\n\n" .

            "SELF-CHECK before outputting:\n" .
            "□ Does my first sentence name their specific problem?\n" .
            "□ Did I avoid every forbidden phrase?\n" .
            "□ Is there exactly ONE question at the end?\n" .
            "□ Zero bullet points or lists?\n" .
            "□ Word count between 100-120?\n\n" .

            "Now write the proposal:\n";
    }

    /**
     * Build job description section
     */
    private function buildJobDescriptionSection(string $jobDescription): string
    {
        $trimmed = substr(trim($jobDescription), 0, self::JOB_DESC_TRIM);
        return "JOB DESCRIPTION:\n{$trimmed}\n\n";
    }

    /**
     * Format user skills for the prompt
     */
    private function formatUserSkills(array $skills): string
    {
        if (empty($skills)) {
            return 'Various technical skills';
        }

        $skillNames = [];

        foreach ($skills as $skill) {
            if (!is_array($skill) || empty($skill['name'])) {
                continue;
            }
            $skillNames[] = $skill['name'];
        }

        if (empty($skillNames)) {
            return 'Various technical skills';
        }

        return implode(', ', array_slice($skillNames, 0, self::MAX_SKILLS));
    }

    /**
     * Get successful proposal patterns for controlled learning
     */
    private function getSuccessfulProposalPatterns(string $userId, string $jobType): array
    {
        return SuccessfulProposalPattern::where('user_id', $userId)
            ->where('job_type', $jobType)
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_PATTERNS)
            ->get()
            ->toArray();
    }

    private function buildJobPainPointExtract(string $jobDescription, array $jobAnalysis): string
    {
        if (!empty($jobAnalysis['pain_point'])) {
            return $jobAnalysis['pain_point'];
        }

        $problemSignals = [
            'struggling', 'need help', 'having trouble', 'broken', 'failing',
            'not working', 'can\'t', 'cannot', 'slow', 'outdated', 'migration',
            'rebuild', 'fix', 'urgent', 'ASAP', 'deadline', 'behind',
            'overwhelmed', 'manual process', 'time-consuming', 'inefficient',
            'error-prone', 'automate', 'scale', 'growing fast', 'too much time',
            'repetitive', 'messy', 'disorganized', 'no system', 'currently doing',
            'every day we', 'every week we', 'spending hours',
        ];

        $sentences = preg_split('/(?<=[.!?])\s+/', $jobDescription, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sentences as $sentence) {
            foreach ($problemSignals as $signal) {
                if (stripos($sentence, $signal) !== false) {
                    return trim(substr($sentence, 0, 120));
                }
            }
        }

        return '';
    }

    private function resolveClientTier(string $spending): string
    {
        if (preg_match('/\$(\d+)([KM])/i', $spending, $m)) {
            $amount = (int) $m[1];
            $unit   = strtoupper($m[2]);

            if ($unit === 'M' || ($unit === 'K' && $amount >= 50)) {
                return 'enterprise';
            }
            if ($unit === 'K' && $amount >= 10) {
                return 'established';
            }
        }

        return 'standard';
    }

    private function resolveToneGuidance(string $tier): string
    {
        return match ($tier) {
            'enterprise'  => 'Write peer-to-peer — this client has hired many freelancers. Skip reassurances. Be direct and strategic.',
            'established' => 'Confident but warm — they know what they want. Show you understand their business, not just the task.',
            default       => 'Friendly and professional — build trust quickly without overselling. Clarity beats cleverness.',
        };
    }

    /**
     * Convert a raw hook line into a style description
     * so the AI learns the pattern without copying the text.
     */
    private function describeHookStyle(string $hookLine): string
    {
        $wordCount = str_word_count($hookLine);
        $startsWithProblem = preg_match(
            '/^(your|the|this|when|if|automating|fixing|building)/i',
            trim($hookLine)
        );

        $style = "~{$wordCount} words";

        if ($startsWithProblem) {
            $style .= ', started with client problem (not with "I")';
        }

        if (str_contains(strtolower($hookLine), 'manual')) {
            $style .= ', called out manual work specifically';
        }
        if (str_contains(strtolower($hookLine), 'automat')) {
            $style .= ', led with automation outcome';
        }
        if (str_contains(strtolower($hookLine), 'time') ||
            str_contains(strtolower($hookLine), 'hour')) {
            $style .= ', referenced time saving';
        }

        return $style;
    }
}
