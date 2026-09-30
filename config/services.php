<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file to locate the various service credentials.
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

    'inventory_ai' => [
        'provider' => env('INVENTORY_AI_PROVIDER', 'cloudflare'),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'inventory_vision_model' => env('CLOUDFLARE_INVENTORY_VISION_MODEL', '@cf/meta/llama-3.2-11b-vision-instruct'),
        'inventory_consolidation_model' => env('CLOUDFLARE_INVENTORY_CONSOLIDATION_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
        'base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'inventory_model' => env('OPENAI_INVENTORY_MODEL', 'gpt-5.6-terra'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

];
