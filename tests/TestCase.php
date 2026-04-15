<?php

namespace Tests;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Route;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to English for consistent test messages
        app()->setLocale('en');

        $this->assertApplicationRoutesHaveUniqueNames();

        // Disable CSRF token verification for tests
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Safety check: ensure we're using SQLite, not MySQL
        $connection = config('database.default');

        // If running in CI/Sail, we might need to override config if environment variables aren't picking up
        if (config('app.env') === 'testing' && $connection !== 'sqlite') {
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

        // Telescope: hard-disable during tests (avoids DB connection attempts to the default mysql telescope storage)
        config(['telescope.enabled' => false]);
        config(['telescope.storage.database.connection' => 'sqlite']);

        // Disable email sending during tests - use 'array' driver which stores emails in memory
        config(['mail.default' => 'array']);

        // Use default mail receiver driver instead of microsoft-graph to prevent GraphMailService instantiation
        config(['mail-receiver.default' => 'webklex-imap']);
    }

    /**
     * Duplicate ->name() registrations cause route() to resolve only one URI; parameters like id are
     * emitted as query strings (?id=) so the wrong route matches and controllers return 302 redirects.
     */
    protected function assertApplicationRoutesHaveUniqueNames(): void
    {
        /** @var array<string, list<string>> $byName */
        $byName = [];

        foreach ($this->app['router']->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }

            $byName[$name][] = $this->formatRouteForDuplicateMessage($route);
        }

        $duplicates = array_filter($byName, fn (array $routes): bool => count($routes) > 1);

        $message = "Duplicate named routes break route() URL generation (tests often see 302 instead of 200). Each ->name() must be unique.\n";
        foreach ($duplicates as $name => $lines) {
            $message .= sprintf("  '%s' (%d): %s\n", $name, count($lines), implode(' | ', $lines));
        }

        $this->assertEmpty($duplicates, $message);
    }

    protected function formatRouteForDuplicateMessage(Route $route): string
    {
        $verbs = $route->methods();
        $verb = $verbs[0] ?? '?';
        $domain = $route->getDomain();
        $uri = ltrim($route->uri(), '/');
        $domainPrefix = $domain !== null ? $domain.'/' : '';

        return strtoupper($verb).' '.$domainPrefix.$uri;
    }
}
