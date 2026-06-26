<?php

use App\Services\Mail\MicrosoftGraphMailTransport;
use App\Services\Mail\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address' => 'crm@example.com',
                'graph'   => [
                    'tenant_id'     => 'test-tenant',
                    'client_id'     => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ],
        ],
        // Clear the allowlist and set env=production so validateRecipients() passes through.
        // Without this the real MAIL_SEND_ONLY_ACCEPT from the test env blocks patient@example.com.
        'mail.send_only_accept'    => null,
        'app.env'                  => 'production',
    ]);
});

function graphTestEmail(): Email
{
    return (new Email)
        ->from('crm@example.com')
        ->to('patient@example.com')
        ->subject('Test')
        ->html('<p>Test</p>');
}

test('returns SentMessage on successful send', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'valid-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*'       => Http::response('', 202),
    ]);

    $transport = new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);

    expect($transport->send(graphTestEmail()))->toBeInstanceOf(SentMessage::class);
});

test('retries with a fresh token when Graph returns 401 InvalidAuthenticationToken', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'expired-token', 'expires_in' => 3600])
            ->push(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::sequence()
            ->push(json_encode(['error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Lifetime validation failed, the token is expired.']]), 401)
            ->push('', 202),
    ]);

    $transport = new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);

    $result = $transport->send(graphTestEmail());

    expect($result)->toBeInstanceOf(SentMessage::class);
    Http::assertSentCount(4); // 2 token fetches + 2 Graph API calls
});

test('does not retry on a non-auth graph error', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        'graph.microsoft.com/*'       => Http::response(json_encode(['error' => ['code' => 'ServiceUnavailable', 'message' => 'Service unavailable.']]), 503),
    ]);

    $transport = new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);

    expect(fn () => $transport->send(graphTestEmail()))->toThrow(Exception::class);
    Http::assertSentCount(2); // 1 token + 1 Graph call (no retry)
});

test('throws when retry after token refresh also fails', function () {
    // Use a callable fake so EVERY HTTP request returns 401, no URL-pattern matching needed.
    // Mock the token service so no login requests are made at all.
    Http::fake(fn () => Http::response(
        json_encode(['error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'expired']]),
        401
    ));

    $tokenService = $this->createMock(MicrosoftGraphTokenService::class);
    $tokenService->method('getAccessToken')->willReturn('any-token');

    $transport = new MicrosoftGraphMailTransport($tokenService);

    expect(fn () => $transport->send(graphTestEmail()))->toThrow(Exception::class, 'Failed to send email');
});
