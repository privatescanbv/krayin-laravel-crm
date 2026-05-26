<?php

use App\Exceptions\Mail\EmailSendingBlockedException;
use App\Services\Mail\MicrosoftGraphMailTransport;

test('blocked recipients throw EmailSendingBlockedException', function () {
    config([
        'mail.send_only_accept' => '*@privatescan.nl',
        'mail.graph.mailbox'    => 'crm@privatescan.nl',
    ]);

    $transport = app(MicrosoftGraphMailTransport::class);
    $method = new ReflectionMethod($transport, 'validateRecipients');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($transport, [
        ['emailAddress' => ['address' => 'blocked@gmail.com', 'name' => 'Blocked']],
    ], [], []))->toThrow(EmailSendingBlockedException::class);
});
