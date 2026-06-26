<?php

use App\Services\Mail\GraphMailService;
use App\Services\Mail\MailboxConfig;
use Illuminate\Support\Facades\Http;
use Webkul\Email\Enums\EmailFolderEnum;

test('graph mail service uses mailbox-specific token when syncing', function () {
    Http::fake([
        'login.microsoftonline.com/hp-tenant/*' => Http::response(['access_token' => 'hp-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*'                 => Http::response(['value' => []], 200),
    ]);

    config([
        'mail.mailboxes' => [
            'herniapoli' => [
                'address'     => 'hp@example.com',
                'folder_name' => EmailFolderEnum::INBOX_HERNIAPOLI->value,
                'graph'       => [
                    'tenant_id'     => 'hp-tenant',
                    'client_id'     => 'hp-client',
                    'client_secret' => 'hp-secret',
                ],
            ],
        ],
    ]);

    $service = app(GraphMailService::class);
    $service->configureMailbox('hp@example.com', 'herniapoli');

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('fetchMessages');
    $method->setAccessible(true);
    $method->invoke($service);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'login.microsoftonline.com/hp-tenant/'));
});

test('mailbox config resolves credentials from mailboxes entry only', function () {
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address' => 'crm@example.com',
                'graph'   => [
                    'tenant_id'     => 'ps-tenant',
                    'client_id'     => 'ps-client',
                    'client_secret' => 'ps-secret',
                ],
            ],
        ],
    ]);

    expect(MailboxConfig::graphCredentials('privatescan')['tenant_id'])->toBe('ps-tenant')
        ->and(MailboxConfig::address('privatescan'))->toBe('crm@example.com');
});
