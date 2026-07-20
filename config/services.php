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
    'otp' => [
        'return_in_response' => env('OTP_RETURN_IN_RESPONSE', false),
    ],

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

    'payments' => [
        'commission_rate' => (float) env('PLATFORM_COMMISSION_PERCENTAGE', 15.0),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'firebase' => (function () {
        $config = [
            'enabled' => (bool) env('FIREBASE_ENABLED', true),
            'force_enable' => (bool) env('FIREBASE_FORCE_ENABLE', false),
            'project_id' => env('FIREBASE_PROJECT_ID'),
            'client_email' => env('FIREBASE_CLIENT_EMAIL'),
            'private_key' => env('FIREBASE_PRIVATE_KEY'),
            'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
            'client_id' => env('FIREBASE_CLIENT_ID'),
            'token_uri' => env('FIREBASE_TOKEN_URI'),
            'testing_allow_real_calls' => (bool) env('FIREBASE_TESTING_ALLOW_REAL_CALLS', false),
        ];

        $serviceAccount = env('FIREBASE_SERVICE_ACCOUNT');
        if ($serviceAccount) {
            $filePath = (str_starts_with($serviceAccount, '/') || str_starts_with($serviceAccount, '\\') || (strlen($serviceAccount) > 1 && $serviceAccount[1] === ':'))
                ? $serviceAccount
                : base_path($serviceAccount);

            if (! file_exists($filePath)) {
                throw new \InvalidArgumentException("Firebase service account file not found at: {$filePath}");
            }

            $json = file_get_contents($filePath);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid Firebase service account JSON: " . json_last_error_msg());
            }

            $config['project_id'] = $config['project_id'] ?: ($data['project_id'] ?? null);
            $config['client_email'] = $config['client_email'] ?: ($data['client_email'] ?? null);
            $config['private_key'] = $config['private_key'] ?: ($data['private_key'] ?? null);
            $config['private_key_id'] = $config['private_key_id'] ?: ($data['private_key_id'] ?? null);
            $config['client_id'] = $config['client_id'] ?: ($data['client_id'] ?? null);
            $config['token_uri'] = $config['token_uri'] ?: ($data['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        } else {
            $config['token_uri'] = $config['token_uri'] ?: 'https://oauth2.googleapis.com/token';
        }

        return $config;
    })(),

];
