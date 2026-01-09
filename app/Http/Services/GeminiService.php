<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
        $this->model = 'gemini-1.5-flash'; // Fast and efficient model
        $this->temperature = 0.4; // Balanced creativity
    }

    /**
     * Generate proposal content using Gemini
     * 
     * @param string $prompt
     * @return array
     */
    public function generateProposal(string $prompt): array
    {
        try {
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
            
            $response = Http::timeout(30)->post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'maxOutputTokens' => 300, // ~120-150 words
                    'topP' => 0.8,
                    'topK' => 40
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                    [
                        'category' => 'HARM_CATEGORY_HATE_SPEECH',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ]
                ]
            ]);

            $data = $response->json();
            
            if ($response->successful() && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $content = trim($data['candidates'][0]['content']['parts'][0]['text']);
                
                // Calculate approximate tokens (Gemini doesn't provide exact count)
                $tokensUsed = isset($data['usageMetadata']) 
                    ? ($data['usageMetadata']['promptTokenCount'] + $data['usageMetadata']['candidatesTokenCount'])
                    : (int)(strlen($prompt) / 4 + strlen($content) / 4);
                
                return [
                    'content' => $content,
                    'tokens_used' => $tokensUsed,
                    'model_used' => $this->model,
                    'success' => true
                ];
            }

            Log::error('Gemini API Error', ['response' => $data]);
            throw new \Exception('Failed to generate proposal content from Gemini');

        } catch (\Exception $e) {
            Log::error('Gemini Service Error', [
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
     * Test Gemini connection
     */
    public function testConnection(): bool
    {
        try {
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
            
            $response = Http::post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Hello, this is a connection test. Please respond with "Connection successful".'
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 10
                ]
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Gemini Connection Test Failed', ['error' => $e->getMessage()]);
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
     * Get available Gemini models
     */
    public function getAvailableModels(): array
    {
        return [
            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Fast, Recommended)',
            'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B (Faster, Lower Cost)',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro (High Quality)',
            'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
        ];
    }

    /**
     * Check if Gemini is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
