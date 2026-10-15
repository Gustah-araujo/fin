<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure AI providers for the Laravel AI SDK. Each provider can
    | specify a driver, credentials, and default models for various
    | capabilities (text, images, embeddings, etc.).
    |
    */

    'providers' => [

        'opencode' => [
            'driver' => 'openai-compatible',
            'url' => 'https://opencode.ai/inference/openai/v1',
            'key' => env('OPENCODE_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENCODE_MODEL', 'glm-5.3-flash'),
                ],
            ],
        ],

    ],

];
