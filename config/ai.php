<?php

return [
    'default' => env('CREDITSOFT_AI_DEFAULT_PROVIDER', 'openrouter_creditsoft'),
    'default_for_images' => env('CREDITSOFT_AI_IMAGE_PROVIDER', 'openai'),
    'default_for_audio' => env('CREDITSOFT_AI_AUDIO_PROVIDER', 'openai'),
    'default_for_transcription' => env('CREDITSOFT_AI_TRANSCRIPTION_PROVIDER', 'openai'),
    'default_for_embeddings' => env('CREDITSOFT_AI_EMBEDDINGS_PROVIDER', 'openai'),
    'default_for_reranking' => env('CREDITSOFT_AI_RERANKING_PROVIDER', 'cohere'),

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'ollama_cloud' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_CLOUD_API_KEY', ''),
            'url' => 'https://ollama.com',
            'models' => [
                'text' => [
                    'default' => env('OLLAMA_CLOUD_TEXT_MODEL', 'nemotron-3-super:cloud'),
                    'cheapest' => env('OLLAMA_CLOUD_CHEAPEST_MODEL', 'nemotron-mini'),
                    'smartest' => env('OLLAMA_CLOUD_SMARTEST_MODEL', 'nemotron-3-super:cloud'),
                ],
            ],
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter_creditsoft' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENROUTER_TEXT_MODEL', 'arcee-ai/trinity-large-thinking'),
                    'cheapest' => env('OPENROUTER_CHEAPEST_MODEL', 'nvidia/llama-3.1-nemotron-nano-8b-v1'),
                    'smartest' => env('OPENROUTER_SMARTEST_MODEL', 'arcee-ai/trinity-large-thinking'),
                ],
            ],
        ],
    ],
];
