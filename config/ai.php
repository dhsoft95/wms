<?php

declare(strict_types=1);

return [
    'provider' => 'gemini',

    'gemini' => [
        'api_key'    => env('GEMINI_API_KEY'),
        'model'      => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'base_url'   => 'https://generativelanguage.googleapis.com/v1beta',
        'timeout'    => 30,
        'max_tokens' => 600,
        'temperature' => 0.2,
    ],

    'enabled' => (bool) env('AI_SUMMARIES_ENABLED', false),
];
