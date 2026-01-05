<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = 'https://api.openai.com/v1';
        $this->model = 'gpt-4o-mini';
        $this->temperature = 0.4;
    }

    /**
     * Generate proposal content using OpenAI
     * 
     * @param string $prompt
     * @return array
     */
    public function generateProposal(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/chat/completions', [
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
                'max_tokens' => 200, // Limit for ~120 words
            ]);

            $data = $response->json();
            
            if ($response->successful() && isset($data['choices'][0]['message']['content'])) {
                return [
                    'content' => trim($data['choices'][0]['message']['content']),
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                    'model_used' => $this->model,
                    'success' => true
                ];
            }

            Log::error('OpenAI API Error', ['response' => $data]);
            throw new \Exception('Failed to generate proposal content');

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'content' => null,
                'tokens_used' => 0,
                'model_used' => $this->model,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test OpenAI connection
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('OpenAI Connection Test Failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Set custom temperature
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = max(0.0, min(1.0, $temperature));
        return $this;
    }

    /**
     * Set custom model
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }
}
