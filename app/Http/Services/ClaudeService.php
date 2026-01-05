<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->baseUrl = 'https://api.anthropic.com/v1';
        $this->model = 'claude-3-5-sonnet-20241022'; // Latest Claude model
        $this->temperature = 0.4;
    }

    /**
     * Generate proposal content using Claude
     * 
     * @param string $prompt
     * @return array
     */
    public function generateProposal(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->timeout(30)->post($this->baseUrl . '/messages', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 200, // Limit for ~120 words
            ]);

            $data = $response->json();
            
            if ($response->successful() && isset($data['content'][0]['text'])) {
                return [
                    'content' => trim($data['content'][0]['text']),
                    'tokens_used' => $data['usage']['input_tokens'] + $data['usage']['output_tokens'] ?? 0,
                    'model_used' => $this->model,
                    'success' => true
                ];
            }

            Log::error('Claude API Error', ['response' => $data]);
            throw new \Exception('Failed to generate proposal content from Claude');

        } catch (\Exception $e) {
            Log::error('Claude Service Error', [
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
     * Test Claude connection
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->post($this->baseUrl . '/messages', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello, this is a connection test. Please respond with "Connection successful".'
                    ]
                ],
                'max_tokens' => 10
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Claude Connection Test Failed', ['error' => $e->getMessage()]);
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

    /**
     * Get available Claude models
     */
    public function getAvailableModels(): array
    {
        return [
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Recommended)',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Faster, Cheaper)',
            'claude-3-opus-20240229' => 'Claude 3 Opus (Highest Quality)',
        ];
    }
}
