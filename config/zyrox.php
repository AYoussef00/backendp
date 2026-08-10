<?php

return [
    'agent_version' => env('ZYROX_AGENT_VERSION', '1.0.0'),
    'heartbeat_grace_seconds' => (int) env('ZYROX_HEARTBEAT_GRACE', 120),
    'metrics_retention_days' => (int) env('ZYROX_METRICS_RETENTION_DAYS', 7),
    'jobs_retention_days' => (int) env('ZYROX_JOBS_RETENTION_DAYS', 30),
    'allowed_file_roots' => [
        '/var/www',
        '/home',
        '/srv',
        '/opt',
    ],
    'blocked_paths' => [
        '/',
        '/etc/shadow',
        '/etc/ssh',
        '/root',
        '/proc',
        '/sys',
        '/boot',
    ],
    'allowed_services' => [
        'nginx',
        'apache2',
        'httpd',
        'php-fpm',
        'php8.3-fpm',
        'php8.2-fpm',
        'mysql',
        'mariadb',
        'redis',
        'redis-server',
        'supervisor',
        'docker',
    ],
];
