<?php

use App\Exceptions\Mail\EmailSendingBlockedException;
use App\Services\Mail\MicrosoftGraphMailTransport;

test('blocked recipients throw EmailSendingBlockedException', function () {
    config([
        'mail.send_only_accept' => '*@privatescan.nl',
        'mail.mailboxes'        => [
            'privatescan' => [
                'address' => 'crm@privatescan.nl',
                'graph'   => [
                    'tenant_id'     => 'test-tenant',
                    'client_id'     => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ],
        ],
    ]);

    $transport = app(MicrosoftGraphMailTransport::class);
    $method = new ReflectionMethod($transport, 'validateRecipients');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($transport, [
        ['emailAddress' => ['address' => 'blocked@gmail.com', 'name' => 'Blocked']],
    ], [], []))->toThrow(EmailSendingBlockedException::class);
});
