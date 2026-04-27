<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->baseUrl = config('services.groq.base_url', 'https://api.groq.com/openai/v1');
        $this->model = config('services.groq.model', 'llama-3.1-8b-instant');
        $this->temperature = (float) config('ai.generation.temperature', 0.4);
        $this->timeout = (int) config('services.groq.timeout', 60);
    }

    public function generateProposal(string $prompt): array
    {
        if (empty($this->apiKey)) {
            return [
                'content' => null,
                'tokens_used' => 0,
                'model_used' => $this->model,
                'success' => false,
                'error' => 'Groq API key not configured'
            ];
        }

        $maxRetries = 3;
        $baseDelay = 2; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout($this->timeout)->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert freelancer proposal writer and job analyst. Follow the prompt instructions exactly and return valid JSON response as specified.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => $this->temperature,
                    'max_tokens' => 800,
                ]);

                $data = $response->json();

                if ($response->successful() && isset($data['choices'][0]['message']['content'])) {
                    return [
                        'content' => trim($data['choices'][0]['message']['content']),
                        'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                        'model_used' => $this->model,
                        'success' => true,
                    ];
                }

                // Handle rate limit specifically
                if (isset($data['error']['code']) && $data['error']['code'] === 'rate_limit_exceeded') {
                    $retryAfter = $data['error']['message'] ?? '';
                    preg_match('/try again in ([\d.]+)s/', $retryAfter, $matches);
                    $delay = isset($matches[1]) ? (float)$matches[1] + 1 : ($baseDelay * $attempt);
                    
                    if ($attempt < $maxRetries) {
                        Log::warning("Groq rate limit hit, retrying in {$delay}s", [
                            'attempt' => $attempt,
                            'max_retries' => $maxRetries,
                            'response' => $data
                        ]);
                        sleep($delay);
                        continue;
                    }
                }

                Log::error('Groq API Error', ['response' => $data]);
                throw new \Exception('Failed to generate proposal content from Groq');
            } catch (\Exception $e) {
                Log::error('Groq Service Error', [
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'trace' => $e->getTraceAsString(),
                ]);

                // If this is the last attempt, return failure
                if ($attempt === $maxRetries) {
                    return [
                        'content' => null,
                        'tokens_used' => 0,
                        'model_used' => $this->model,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                // For non-rate-limit errors, wait before retry
                sleep($baseDelay * $attempt);
            }
        }

        return [
            'content' => null,
            'tokens_used' => 0,
            'model_used' => $this->model,
            'success' => false,
            'error' => 'Max retries exceeded for Groq API',
        ];
    }
}
