<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'failover'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You may specify which one you're using throughout
    | your configuration file. You may also add additional drivers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [
        'microsoft-graph' => [
            'transport' => 'microsoft-graph',
        ],

        'smtp' => [
            'transport'    => 'smtp',
            'host'         => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port'         => env('MAIL_PORT', 587),
            'encryption'   => env('MAIL_ENCRYPTION', 'tls'),
            'username'     => env('MAIL_USERNAME'),
            'password'     => env('MAIL_PASSWORD'),
            'timeout'      => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'domain' => env('MAILGUN_DOMAIN'),
            // 'secret' => env('MAILGUN_SECRET'),
            // 'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            // 'scheme' => 'https',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers'   => [
                'microsoft-graph',
                'smtp',
                'log',
            ],
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers'   => [
                'smtp',
                'log',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Microsoft Graph API integration
    |
    */

    'graph' => [
        'client_id'     => env('GRAPH_CLIENT_ID'),
        'tenant_id'     => env('GRAPH_TENANT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'mailbox'       => env('GRAPH_MAILBOX', 'crm@privatescan.nl'),
        'sender_domain' => env('GRAPH_SENDER_DOMAIN', 'crm.private-scan.nl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Safety Net Configuration
    |--------------------------------------------------------------------------
    |
    | Restrict email sending to specific domains/patterns for safety.
    | Use semicolon-separated wildcard patterns (e.g., *@privatescan.nl;*@mbsoftware.nl)
    | If not set, all emails are allowed.
    |
    */

    'send_only_accept' => env('MAIL_SEND_ONLY_ACCEPT', null),

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | The domain used for generating unique email IDs
    |
    */

    'domain' => env('MAIL_DOMAIN', 'example.com'),

    /*
    |--------------------------------------------------------------------------
    | Email Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep email logs before cleanup
    |
    */

    'log_retention_days' => env('MAIL_LOG_RETENTION_DAYS', 7),

];
