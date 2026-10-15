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
            'driver' => 'openai-compatible',
            'url' => env('OPENCODE_BASE_URL', 'https://opencode.ai/inference/openai/v1'),
            'key' => env('OPENCODE_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENCODE_MODEL', 'glm-5.3-flash'),
                ],
            ],
        ],
    ],

    // Mantido para compatibilidade com timeout legado
    'timeout' => env('OPENCODE_TIMEOUT', 120),
];
