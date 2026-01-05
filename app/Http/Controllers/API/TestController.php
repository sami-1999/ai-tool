<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OpenAIService;
use App\Http\Services\ClaudeService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(
        private OpenAIService $openAIService,
        private ClaudeService $claudeService
    ) {}

    /**
     * Test OpenAI connection and model
     */
    public function testOpenAI()
    {
        try {
            // Test 1: Check if API key is configured
            if (!config('services.openai.api_key')) {
                return ApiResponse::error('OpenAI API key not configured', 400);
            }

            // Test 2: Test connection
            $connectionTest = $this->openAIService->testConnection();
            if (!$connectionTest) {
                return ApiResponse::error('Failed to connect to OpenAI API', 400);
            }

            // Test 3: Test proposal generation with sample data
            $testPrompt = "Generate a short test proposal for a web development project. Keep it under 50 words.";
            
            $result = $this->openAIService->generateProposal($testPrompt);
            
            if (!$result['success']) {
                return ApiResponse::error('OpenAI generation failed: ' . $result['error'], 400);
            }

            return ApiResponse::success([
                'connection_status' => 'Connected ✅',
                'api_key_configured' => 'Yes ✅', 
                'model' => 'gpt-4o-mini',
                'test_generation' => [
                    'prompt' => $testPrompt,
                    'response' => $result['content'],
                    'tokens_used' => $result['tokens_used']
                ],
                'message' => 'OpenAI integration is working perfectly!'
            ], 'OpenAI test completed successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('OpenAI test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test the complete AI proposal generation flow
     */
    public function testProposalGeneration(Request $request)
    {
        try {
            $testJobDescription = $request->input('job_description', 
                'Looking for a Laravel developer to build a simple API for managing tasks. 
                Need someone with PHP, Laravel, and database experience.'
            );

            // Import the services for testing
            $jobAnalysisService = app(\App\Http\Services\JobAnalysisService::class);
            
            // Test job analysis
            $jobAnalysis = $jobAnalysisService->analyze($testJobDescription);
            
            // Test prompt building (simplified version)
            $testPrompt = "
            FREELANCER BACKGROUND:
            - Title: Full Stack Developer
            - Experience: 5 years
            - Tone: Professional

            RULES:
            - Maximum 120 words
            - Human, conversational tone
            - No buzzwords
            - Ask 1 smart question

            JOB DESCRIPTION:
            $testJobDescription

            Generate a compelling proposal:
            ";

            $result = $this->openAIService->generateProposal($testPrompt);

            if (!$result['success']) {
                return ApiResponse::error('Proposal generation failed: ' . $result['error'], 400);
            }

            return ApiResponse::success([
                'job_analysis' => $jobAnalysis,
                'generated_proposal' => [
                    'content' => $result['content'],
                    'tokens_used' => $result['tokens_used'],
                    'model_used' => $result['model_used']
                ],
                'test_status' => 'SUCCESS ✅'
            ], 'Full proposal generation test completed');

        } catch (\Exception $e) {
            return ApiResponse::error('Proposal generation test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test Claude connection and model
     */
    public function testClaude()
    {
        try {
            // Test 1: Check if API key is configured
            if (!config('services.claude.api_key')) {
                return ApiResponse::error('Claude API key not configured', 400);
            }

            // Test 2: Test connection
            $connectionTest = $this->claudeService->testConnection();
            if (!$connectionTest) {
                return ApiResponse::error('Failed to connect to Claude API', 400);
            }

            // Test 3: Test proposal generation with sample data
            $testPrompt = "Generate a short test proposal for a web development project. Keep it under 50 words.";
            
            $result = $this->claudeService->generateProposal($testPrompt);
            
            if (!$result['success']) {
                return ApiResponse::error('Claude generation failed: ' . $result['error'], 400);
            }

            return ApiResponse::success([
                'connection_status' => 'Connected ✅',
                'api_key_configured' => 'Yes ✅', 
                'model' => 'claude-3-5-sonnet-20241022',
                'available_models' => $this->claudeService->getAvailableModels(),
                'test_generation' => [
                    'prompt' => $testPrompt,
                    'response' => $result['content'],
                    'tokens_used' => $result['tokens_used']
                ],
                'message' => 'Claude integration is working perfectly!'
            ], 'Claude test completed successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Claude test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test Claude proposal generation flow
     */
    public function testClaudeProposalGeneration(Request $request)
    {
        try {
            $testJobDescription = $request->input('job_description', 
                'Looking for a Laravel developer to build a simple API for managing tasks. 
                Need someone with PHP, Laravel, and database experience.'
            );

            // Import the services for testing
            $jobAnalysisService = app(\App\Http\Services\JobAnalysisService::class);
            
            // Test job analysis
            $jobAnalysis = $jobAnalysisService->analyze($testJobDescription);
            
            // Test prompt building (simplified version)
            $testPrompt = "
            FREELANCER BACKGROUND:
            - Title: Full Stack Developer
            - Experience: 5 years
            - Tone: Professional

            RULES:
            - Maximum 120 words
            - Human, conversational tone
            - No buzzwords
            - Ask 1 smart question

            JOB DESCRIPTION:
            $testJobDescription

            Generate a compelling proposal:
            ";

            $result = $this->claudeService->generateProposal($testPrompt);

            if (!$result['success']) {
                return ApiResponse::error('Claude proposal generation failed: ' . $result['error'], 400);
            }

            return ApiResponse::success([
                'job_analysis' => $jobAnalysis,
                'generated_proposal' => [
                    'content' => $result['content'],
                    'tokens_used' => $result['tokens_used'],
                    'model_used' => $result['model_used']
                ],
                'test_status' => 'SUCCESS ✅',
                'provider' => 'Claude'
            ], 'Claude proposal generation test completed');

        } catch (\Exception $e) {
            return ApiResponse::error('Claude proposal generation test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Compare OpenAI vs Claude side by side
     */
    public function compareProviders(Request $request)
    {
        try {
            $testPrompt = $request->input('prompt', 'Generate a professional Upwork proposal for a Laravel development project. Keep it under 100 words and ask one relevant question.');

            // Test both providers
            $openAIResult = $this->openAIService->generateProposal($testPrompt);
            $claudeResult = $this->claudeService->generateProposal($testPrompt);

            return ApiResponse::success([
                'prompt' => $testPrompt,
                'openai' => [
                    'success' => $openAIResult['success'],
                    'content' => $openAIResult['content'] ?? null,
                    'tokens_used' => $openAIResult['tokens_used'] ?? 0,
                    'model' => $openAIResult['model_used'] ?? 'gpt-4o-mini',
                    'error' => $openAIResult['error'] ?? null
                ],
                'claude' => [
                    'success' => $claudeResult['success'],
                    'content' => $claudeResult['content'] ?? null,
                    'tokens_used' => $claudeResult['tokens_used'] ?? 0,
                    'model' => $claudeResult['model_used'] ?? 'claude-3-5-sonnet',
                    'error' => $claudeResult['error'] ?? null
                ],
                'comparison' => [
                    'openai_word_count' => $openAIResult['success'] ? str_word_count($openAIResult['content']) : 0,
                    'claude_word_count' => $claudeResult['success'] ? str_word_count($claudeResult['content']) : 0,
                ]
            ], 'Provider comparison completed');

        } catch (\Exception $e) {
            return ApiResponse::error('Provider comparison failed: ' . $e->getMessage(), 500);
        }
    }
}
