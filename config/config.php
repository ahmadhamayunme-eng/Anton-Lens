<?php
return [
    'app_name' => 'Anton Lens',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u419638158_antonlens',
        'user' => getenv('DB_USER') ?: 'u419638158_antonlens01',
        'pass' => getenv('DB_PASS') ?: 'Primaldevs01@@',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'app_secret' => getenv('APP_SECRET') ?: 'change-me',
        'login_rate_limit_attempts' => 5,
        'login_rate_limit_window_seconds' => 900,
        'proxy_rate_limit_per_minute' => 60,
        'session_days' => 7,
    ],
    'screenshot' => [
        'provider' => 'placeholder',
    ],
];
