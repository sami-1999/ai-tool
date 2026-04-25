<?php

namespace App\Http\Services;

class ProposalQualityService
{
    private const FORBIDDEN_PHRASES = [
        'I am excited', 'I\'m excited', 'excited to', 'passionate', 'guru',
        'expert', 'leverage', 'I would be delighted', 'I am confident I can deliver',
        'It would be my pleasure', 'I am confident', 'hard-working', 'hardworking',
        'team player', 'detail-oriented', 'self-motivated', 'results-driven',
        'synergy', 'proactive', 'perfect fit', 'perfect candidate',
        'I assure you', 'rest assured', 'I guarantee', 'best regards', 'dear client'
    ];

    private const COMMON_WORDS = [
        'the', 'and', 'for', 'with', 'that', 'this', 'have', 'are',
        'from', 'will', 'would', 'could', 'should', 'can', 'may',
        'has', 'had', 'been', 'being', 'was', 'were', 'is', 'am'
    ];

    /**
     * Validate AI-generated proposal before saving
     *
     * @param string $proposalContent
     * @param string $jobDescription
     * @return array
     */
    public function validate(string $proposalContent, string $jobDescription): array
    {
        $checks = [
            'word_count' => $this->checkWordCount($proposalContent),
            'forbidden_phrases' => $this->checkForbiddenPhrases($proposalContent),
            'has_question' => $this->checkQuestion($proposalContent),
            'no_bullets' => $this->checkNoBullets($proposalContent),
            'opener' => $this->checkOpener($proposalContent),
            'relevance' => $this->checkRelevance($proposalContent, $jobDescription),
        ];

        $passedChecks = array_filter($checks, fn($check) => $check['passed']);
        $failedChecks = array_keys(array_filter($checks, fn($check) => !$check['passed']));
        
        $score = (count($passedChecks) / count($checks)) * 100;
        $passed = empty($failedChecks);

        return [
            'passed' => $passed,
            'score' => (int)$score,
            'checks' => $checks,
            'failed_checks' => $failedChecks,
            'regenerate' => $score < 60,
        ];
    }

    /**
     * Check word count (80-150 words, sweet spot 100-120)
     */
    private function checkWordCount(string $content): array
    {
        $words = str_word_count($content);
        
        if ($words < 80) {
            return [
                'passed' => false,
                'value' => $words,
                'message' => "{$words} words - too short (minimum 80)"
            ];
        }
        
        if ($words > 150) {
            return [
                'passed' => false,
                'value' => $words,
                'message' => "{$words} words - too long (maximum 150)"
            ];
        }
        
        $quality = ($words >= 100 && $words <= 120) ? 'perfect' : 'acceptable';
        
        return [
            'passed' => true,
            'value' => $words,
            'message' => "{$words} words - {$quality}"
        ];
    }

    /**
     * Check for forbidden phrases
     */
    private function checkForbiddenPhrases(string $content): array
    {
        $found = [];
        $lowerContent = strtolower($content);
        
        foreach (self::FORBIDDEN_PHRASES as $phrase) {
            if (str_contains($lowerContent, strtolower($phrase))) {
                $found[] = $phrase;
            }
        }
        
        return [
            'passed' => empty($found),
            'found' => $found,
            'message' => empty($found) ? 'No forbidden phrases' : 'Found: ' . implode(', ', $found)
        ];
    }

    /**
     * Check for exactly one question
     */
    private function checkQuestion(string $content): array
    {
        $questionCount = substr_count($content, '?');
        
        if ($questionCount === 0) {
            return [
                'passed' => false,
                'count' => 0,
                'message' => 'No question found - proposal must end with one question'
            ];
        }
        
        if ($questionCount > 1) {
            return [
                'passed' => false,
                'count' => $questionCount,
                'message' => 'Too many questions - use exactly one'
            ];
        }
        
        return [
            'passed' => true,
            'count' => 1,
            'message' => 'Perfect - one engaging question'
        ];
    }

    /**
     * Check for bullet points or numbered lists
     */
    private function checkNoBullets(string $content): array
    {
        $lines = explode("\n", $content);
        $bulletPatterns = [
            '/^\s*[-*•]\s/',           // - * •
            '/^\s*\d+\.\s/',           // 1. 2. 3.
            '/^\s*\([a-z0-9]+\)\s/i',  // (a) (1)
        ];
        
        foreach ($lines as $line) {
            foreach ($bulletPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    return [
                        'passed' => false,
                        'message' => 'Contains bullet points or lists - use prose only'
                    ];
                }
            }
        }
        
        return [
            'passed' => true,
            'message' => 'No bullets - natural paragraph format'
        ];
    }

    /**
     * Check opener (first word must not be generic)
     */
    private function checkOpener(string $content): array
    {
        $genericOpeners = ['i', 'hi', 'hello', 'dear', 'hey', 'my', 'as'];
        $words = str_word_count($content, 1);
        
        if (empty($words)) {
            return [
                'passed' => false,
                'first_word' => '',
                'message' => 'Proposal is empty'
            ];
        }
        
        $firstWord = strtolower($words[0]);
        
        if (in_array($firstWord, $genericOpeners)) {
            return [
                'passed' => false,
                'first_word' => $words[0],
                'message' => "Generic opener '{$words[0]}' - start mid-thought"
            ];
        }
        
        return [
            'passed' => true,
            'first_word' => $words[0],
            'message' => "Strong opener - starts with '{$words[0]}'"
        ];
    }

    /**
     * Check job relevance by keyword matching
     */
    private function checkRelevance(string $content, string $jobDescription): array
    {
        $keywords = $this->extractKeywords($jobDescription, 3);
        $lowerContent = strtolower($content);
        $matched = [];
        
        foreach ($keywords as $keyword) {
            if (str_contains($lowerContent, strtolower($keyword))) {
                $matched[] = $keyword;
            }
        }
        
        if (empty($matched)) {
            return [
                'passed' => false,
                'matched_keywords' => [],
                'expected' => $keywords,
                'message' => 'Not personalized - no job keywords found'
            ];
        }
        
        return [
            'passed' => true,
            'matched_keywords' => $matched,
            'message' => 'Personalized - mentions: ' . implode(', ', $matched)
        ];
    }

    /**
     * Extract top keywords from job description
     */
    private function extractKeywords(string $text, int $count = 3): array
    {
        $words = str_word_count(strtolower($text), 1);
        $filtered = array_filter($words, function($word) {
            return strlen($word) > 4 && !in_array($word, self::COMMON_WORDS);
        });
        
        $frequency = array_count_values($filtered);
        arsort($frequency);
        
        return array_slice(array_keys($frequency), 0, $count);
    }
}
