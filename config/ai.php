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
];
