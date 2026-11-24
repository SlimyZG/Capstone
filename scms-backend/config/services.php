<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini AI Configuration
    |--------------------------------------------------------------------------
    |
    | PRIVACY & DATA-HANDLING WARNING:
    | Enabling Gemini (GEMINI_ENABLED=true) transmits student concern titles
    | and descriptions to the external Google Gemini API for sentiment,
    | classification, priority suggestion, routing recommendation, and issue
    | summarization. Gemini is disabled by default across all environments and
    | is NEVER automatically enabled merely because GEMINI_API_KEY is set.
    | Require explicit GEMINI_ENABLED=true (use synthetic test concerns only
    | on Free-tier Gemini in local development, and require formal UCLM
    | institutional approval before enabling in production).
    |
    */

    'gemini' => [
        'enabled'     => filter_var(env('GEMINI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_key'     => env('GEMINI_API_KEY', ''),
        'model'       => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'timeout'     => (int) env('GEMINI_TIMEOUT', 35),
        'max_retries' => (int) env('GEMINI_MAX_RETRIES', 1),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
    ],

];
