<?php

return [
    'agent_version' => env('ZYROX_AGENT_VERSION', '1.0.0'),
    'heartbeat_grace_seconds' => (int) env('ZYROX_HEARTBEAT_GRACE', 120),
    'metrics_retention_days' => (int) env('ZYROX_METRICS_RETENTION_DAYS', 7),
    'jobs_retention_days' => (int) env('ZYROX_JOBS_RETENTION_DAYS', 30),
    // local = always execute on the panel host
    // agent = always queue for remote agent
    // auto  = local for configured local server IDs / sites whose config exists here, else agent
    'website_actions' => env('ZYROX_WEBSITE_ACTIONS', 'auto'),
    // Comma-separated server IDs that live on the same machine as the panel
    'local_server_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('ZYROX_LOCAL_SERVER_IDS', '')),
    ))),
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
