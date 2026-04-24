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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert freelancer proposal writer. Generate professional, personalized Upwork proposals that are human-like and compelling.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 220,
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

            Log::error('Groq API Error', ['response' => $data]);
            throw new \Exception('Failed to generate proposal content from Groq');
        } catch (\Exception $e) {
            Log::error('Groq Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'content' => null,
                'tokens_used' => 0,
                'model_used' => $this->model,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
