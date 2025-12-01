<?php

namespace Tests;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to English for consistent test messages
        app()->setLocale('en');

        // Disable CSRF token verification for tests
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Disable Keycloak sync during tests to prevent test users from being synced
        config(['services.keycloak.client_id' => null]);

        // Disable Microsoft Graph mail configuration to prevent any email sending
        config(['mail.graph.client_id' => null]);
        config(['mail.graph.client_secret' => null]);
        config(['mail.graph.tenant_id' => null]);
        config(['mail.graph.mailbox' => null]);
        config(['mail.graph.sender_domain' => null]);

        // Disable email sending during tests - use 'array' driver which stores emails in memory
        config(['mail.default' => 'array']);

        // Use default mail receiver driver instead of microsoft-graph to prevent GraphMailService instantiation
        config(['mail-receiver.default' => 'webklex-imap']);
    }
}
