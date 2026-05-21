<?php

declare(strict_types=1);

return [
    'shipsgo' => [
        'base_url'             => env('SHIPSGO_BASE_URL', 'https://api.shipsgo.com/v2'),
        'api_key'              => env('SHIPSGO_API_KEY'),
        'webhook_secret'       => env('SHIPSGO_WEBHOOK_SECRET'),
        'timeout'              => (int) env('SHIPSGO_TIMEOUT', 15),
        'retry_attempts'       => (int) env('SHIPSGO_RETRY_ATTEMPTS', 3),
        'retry_base_ms'        => (int) env('SHIPSGO_RETRY_BASE_MS', 500),
        'credit_low_threshold' => (int) env('SHIPSGO_CREDIT_LOW_THRESHOLD', 50),
        'webhook_route'        => env('SHIPSGO_WEBHOOK_ROUTE', '/api/v1/webhooks/shipsgo'),
    ],

    'sanitization' => [
        // Forbidden patterns scanned by EnforceMobileSanitization middleware
        // on every JSON response served from a mobile-facing route.
        'forbidden_patterns' => [
            '/shipsgo/i',
        ],
        // Behavior is environment-dependent — see ALIGNMENT_PATCH.md §2.8.
        'throw_envs' => ['local', 'testing'],
    ],

    'idempotency' => [
        'ttl_hours' => (int) env('TRACKING_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'stickers' => [
        'qr_uri_scheme' => 'shipflow://qr/',
        'storage_disk'  => env('TRACKING_STICKER_DISK', 'local'),
    ],
];
