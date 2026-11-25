<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * OpenAI-compatible chat completions endpoint used by the PDF/Word → module import.
     * The model must accept PDF `file` content parts (glm-5.3-flash via Sumopod does).
     */
    'llm' => [
        'base_url' => env('LLM_BASE_URL'),
        'key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'glm-5.3-flash'),
        // Total seconds one answer may take, and seconds without any streamed data before giving up.
        'timeout' => (int) env('LLM_TIMEOUT', 1200),
        'idle_timeout' => (int) env('LLM_IDLE_TIMEOUT', 180),
        'max_output_tokens' => (int) env('LLM_MAX_OUTPUT_TOKENS', 64000),
        'max_attempts' => (int) env('LLM_MAX_ATTEMPTS', 3),
        // Request fields added when the material and questions are written. Thinking is the slow part there
        // (most of the output tokens), while the outline, which needs it, keeps it on. Set to "{}" to keep thinking everywhere.
        'part_options' => json_decode((string) env('LLM_PART_OPTIONS', '{"reasoning_effort":"low"}'), true) ?: [],
        'max_upload_kb' => (int) env('LLM_MAX_UPLOAD_KB', 20480),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
