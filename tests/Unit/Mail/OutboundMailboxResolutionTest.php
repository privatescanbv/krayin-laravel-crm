<?php

use App\Services\Mail\MailboxConfig;
use App\Services\Mail\MicrosoftGraphMailTransport;
use App\Services\Mail\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config([
        'mail.send_only_accept' => null,
        'app.env'               => 'production',
        'mail.mailboxes'        => [
            'privatescan' => [
                'address'      => 'service@privatescan.nl',
                'display_name' => 'PrivateScan',
                'send_as'      => ['crm@privatescan.nl'],
                'graph'        => [
                    'tenant_id'     => 'ps-tenant',
                    'client_id'     => 'ps-client',
                    'client_secret' => 'ps-secret',
                ],
            ],
            'herniapoli' => [
                'address'      => 'service@herniapoli.nl',
                'display_name' => 'HerniaPoli',
                'graph'        => [
                    'tenant_id'     => 'hp-tenant',
                    'client_id'     => 'hp-client',
                    'client_secret' => 'hp-secret',
                ],
            ],
        ],
    ]);
});

test('crm@privatescan.nl alias resolves to privatescan mailbox key', function () {
    expect(MailboxConfig::resolveKeyByAddress('crm@privatescan.nl'))->toBe('privatescan');
});

test('sending with crm@ alias uses privatescan graph mailbox not herniapoli', function () {
    Http::fake([
        'login.microsoftonline.com/ps-tenant/*' => Http::response(['access_token' => 'ps-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*'                 => Http::response('', 202),
    ]);

    $message = (new Email)
        ->from('crm@privatescan.nl')
        ->to('patient@example.com')
        ->subject('Test')
        ->html('<p>Test</p>');
    $message->getHeaders()->addTextHeader(MailboxConfig::MAILBOX_KEY_HEADER, 'privatescan');

    $transport = new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);
    $transport->send($message);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/users/service@privatescan.nl/sendMail')
            && $request->hasHeader('Authorization');
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'login.microsoftonline.com/ps-tenant/');
    });
});
