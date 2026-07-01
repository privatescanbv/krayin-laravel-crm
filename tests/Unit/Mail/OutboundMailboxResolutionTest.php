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

test('generic app from address falls back to graph mailbox to avoid ErrorSendAsDenied', function () {
    // Simulates AFB/system emails where no mailbox_key is set and the From is config('mail.from.address'),
    // which is not a configured mailbox address or send-as alias. Without this fallback the transport
    // would send From: noreply@example.com through service@privatescan.nl → ErrorSendAsDenied.
    config(['mail.mailboxes.privatescan.send_as' => []]); // no send_as aliases

    Http::fake([
        'login.microsoftonline.com/ps-tenant/*' => Http::response(['access_token' => 'ps-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*'                 => Http::response('', 202),
    ]);

    $message = (new Email)
        ->from('noreply@example.com') // generic app address, not a configured mailbox
        ->to('patient@example.com')
        ->subject('AFB Test')
        ->html('<p>Test</p>');
    // No X-Crm-Mailbox-Key header (AFB emails don't set one)

    $transport = new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);
    $transport->send($message);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/users/service@privatescan.nl/sendMail')) {
            return false;
        }
        // From in the payload must equal the graph mailbox, not the generic app address
        $body = $request->data();
        $fromAddress = $body['message']['from']['emailAddress']['address'] ?? null;

        return $fromAddress === 'service@privatescan.nl';
    });
});
