<?php

return [
    'concurrency' => [
        'limit_per_user' => 2,
        'session_ttl' => 30, // seconds
    ],
    'rate_limits' => [
        'upload' => [
            'max_attempts' => 10,
            'decay_minutes' => 60,
        ],
        'playlist' => [
            'max_attempts' => 120,
            'decay_minutes' => 1,
        ],
        'segments' => [
            'max_attempts' => 1000,
            'decay_minutes' => 1,
        ],
    ],
    'webhooks' => [
        'secret' => env('WEBHOOK_SECRET'),
        'url' => env('WEBHOOK_URL'),
        'timeout' => 10, // seconds
        'retry_delays' => [10, 60, 300], // seconds
    ],
    'ffmpeg' => [
        'timeout' => 3600, // seconds
        'threads' => 0, // 0 = auto
    ],
    'storage' => [
        'originals' => 'local',
        'processed' => 'public',
    ],
];