<?php

return [
    /*
     * Comma-separated list of email addresses that receive RevOps alerts.
     * Leave empty to disable the feature entirely.
     * Example: "ops@example.com,mark@example.com"
     */
    'no_lead_alert_recipients' => env('REVOPS_NO_LEAD_ALERT_RECIPIENTS', ''),
];
