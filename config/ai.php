<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'opencode' => [
            'driver' => 'anthropic',
            'url' => env('OPENCODE_BASE_URL', 'https://opencode.ai/inference/anthropic/v1'),
            'key' => env('OPENCODE_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENCODE_MODEL', 'claude-haiku-5-5'),
                ],
            ],
        ],
    ],

    // Mantido para compatibilidade com timeout legado
    'timeout' => env('OPENCODE_TIMEOUT', 120),

    'fin' => [
        'model' => env('FIN_MODEL', 'claude-haiku-5-5'),
        'title_model' => env('FIN_TITLE_MODEL', 'deepseek-v4.1-flash'),
        'pending_ttl' => env('FIN_PENDING_TTL', 30),
    ],
];
