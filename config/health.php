<?php

// Health API contract v1 (.mb-foundation/skills/health-check-api). Alle doelen en drempels komen
// uit deze config; een request kan er niets van kiezen.
return [
    'service' => 'crm',

    // Dedicated read-only token voor /health/report (min. 32 willekeurige bytes). Leeg = report geweigerd.
    'token' => env('HEALTH_REPORT_TOKEN'),

    // Bronnen die /health/ready mogen aanroepen (rechtstreeks binnen het docker-netwerk, zonder proxy-headers).
    'probe_cidrs' => array_filter(explode(',', env('HEALTH_PROBE_CIDRS', '127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1'))),

    // true = /health/ready ook via Traefik (X-Forwarded-For) toestaan; zet alleen aan samen met de
    // monitor-router met eigen ipallowlist (docker-compose-*.yml, HEALTH_MONITOR_WHITELIST).
    'probe_allow_proxied' => (bool) env('HEALTH_PROBE_ALLOW_PROXIED', false),

    'max_requests_per_minute' => 60,
    'check_timeout_ms'        => 1000,
    'total_timeout_ms'        => 5000,

    'queues' => array_filter(explode(',', env('HEALTH_QUEUES', 'default'))),

    // Failed-jobs-drempels komen uit config/failed_jobs.php (zelfde als queue:monitor-failed-jobs).
    // Operator '>=': kritiek eerst, daarna waarschuwing. null = niveau uit.
    'thresholds' => [
        'pending'                => ['operator' => '>=', 'warning' => 100, 'critical' => 500],
        'oldest_pending_seconds' => ['operator' => '>=', 'warning' => 300, 'critical' => 900],
    ],
];
