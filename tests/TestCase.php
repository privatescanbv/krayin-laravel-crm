<?php

namespace Tests;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use RuntimeException;

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

        // Safety check: ensure we're using SQLite, not MySQL
        $connection = config('database.default');

        // If running in CI/Sail, we might need to override config if environment variables aren't picking up
        if (env('APP_ENV') === 'testing' && $connection !== 'sqlite') {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
            $connection = 'sqlite';
        }

        if ($connection !== 'sqlite') {
            throw new RuntimeException(
                "Tests must use SQLite, but found: {$connection}. ".
                'This is a safety check to prevent accidental MySQL usage during tests.'
            );
        }

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
