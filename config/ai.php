<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI providers and settings
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use for proposal generation.
    | Supported providers: "claude", "openai"
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Provider Settings
    |--------------------------------------------------------------------------
    |
    | Settings for each AI provider
    |
    */

    'providers' => [
        'claude' => [
            'name' => 'Claude 3.5 Sonnet',
            'description' => 'Best for creative and human-like proposals',
            'enabled' => env('AI_CLAUDE_ENABLED', true),
        ],
        'openai' => [
            'name' => 'GPT-4o Mini',
            'description' => 'Fast and cost-effective for proposals',
            'enabled' => env('AI_OPENAI_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for proposal generation
    |
    */

    'generation' => [
        'max_words' => env('AI_MAX_WORDS', 120),
        'temperature' => env('AI_TEMPERATURE', 0.4),
        'daily_limit' => env('AI_DAILY_LIMIT', 10),
    ],

];
