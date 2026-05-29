<?php

return [
    /*
     * Comma-separated list of email addresses that receive queue-monitoring alerts.
     * Leave empty to disable alerting entirely.
     * Example: "ops@example.com,dev@example.com"
     */
    'alert_email' => env('FAILED_JOBS_ALERT_EMAIL', ''),

    /*
     * Number of failed jobs that triggers a WARNING email.
     */
    'warning_threshold' => (int) env('FAILED_JOBS_WARNING_THRESHOLD', 5),

    /*
     * Number of failed jobs that triggers a CRITICAL email.
     */
    'critical_threshold' => (int) env('FAILED_JOBS_CRITICAL_THRESHOLD', 10),
];
