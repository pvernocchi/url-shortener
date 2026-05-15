<?php
return [
    'app' => [
        'name'       => 'URL Shortener',
        'url'        => 'https://yourdomain.com',
        'secret'     => 'change-me-32-char-secret-key',
        'debug'      => false,
        'timezone'   => 'UTC',
        'auto_migrate' => true,
        'installed'  => false,
    ],
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
        'prefix'  => 'us_',
    ],
    'security' => [
        'rate_limit_create'   => 10,   // per hour per IP
        'rate_limit_redirect' => 300,  // per hour per IP
        'max_url_length'      => 2048,
        'allowed_protocols'   => ['http', 'https'],
        'blocked_domains'     => [],
        'login_max_attempts'  => 5,
        'login_lockout_mins'  => 15,
    ],
    'analytics' => [
        'enabled'             => true,
        'anonymize_ip'        => true,
        'retention_days'      => 365,
    ],
    'api' => [
        'enabled'  => true,
    ],
    'mail' => [
        'smtp_host'         => 'smtp.example.com',
        'smtp_port'         => 587,
        'smtp_encryption'   => 'tls',   // 'tls' (STARTTLS), 'ssl' (implicit TLS), or 'none'
        'smtp_username'     => '',
        'smtp_password'     => '',
        'smtp_from_address' => 'noreply@example.com',
        'smtp_from_name'    => 'URL Shortener',
        'smtp_logging'      => false,   // set to true to log SMTP conversations for troubleshooting
    ],
];
