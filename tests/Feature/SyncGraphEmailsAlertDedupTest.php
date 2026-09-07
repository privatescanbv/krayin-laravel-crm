<?php

namespace Tests\Feature;

use App\Services\Mail\GraphMailService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Tests\TestCase;

class SyncGraphEmailsAlertDedupTest extends TestCase
{
    /** @var list<Event> */
    private array $sentEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config(['mail.mailboxes' => [
            'privatescan' => [
                'address'     => 'service@example.com',
                'folder_name' => 'Inbox',
                'graph'       => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
            ],
        ]]);

        $events = &$this->sentEvents;

        SentrySdk::getCurrentHub()->bindClient(
            ClientBuilder::create(['dsn' => null])
                ->setTransport(new class($events) implements TransportInterface
                {
                    public function __construct(private array &$events) {}

                    public function send(Event $event): Result
                    {
                        $this->events[] = $event;

                        return new Result(ResultStatus::success());
                    }

                    public function close(?int $timeout = null): Result
                    {
                        return new Result(ResultStatus::success());
                    }
                })
                ->getClient()
        );
    }

    public function test_repeated_connection_failure_reports_to_sentry_only_once()
    {
        $this->fakeSync(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->artisan('emails:sync-graph')->assertSuccessful();
        $this->artisan('emails:sync-graph')->assertSuccessful();
        $this->artisan('emails:sync-graph')->assertSuccessful();

        $this->assertCount(1, $this->sentEvents);
        $this->assertTrue(Cache::has('graph-sync-failed:privatescan'));
    }

    public function test_flag_clears_on_success_so_next_incident_alerts_again()
    {
        $this->fakeSync(fn () => throw new ConnectionException('down'));
        $this->artisan('emails:sync-graph')->assertSuccessful();
        $this->assertCount(1, $this->sentEvents);

        $this->fakeSync(fn () => null);
        $this->artisan('emails:sync-graph')->assertSuccessful();
        $this->assertFalse(Cache::has('graph-sync-failed:privatescan'));

        $this->fakeSync(fn () => throw new ConnectionException('down again'));
        $this->artisan('emails:sync-graph')->assertSuccessful();
        $this->assertCount(2, $this->sentEvents);
    }

    public function test_a_different_error_type_alerts_even_while_one_is_already_flagged()
    {
        $this->fakeSync(fn () => throw new ConnectionException('network down'));
        $this->artisan('emails:sync-graph')->assertSuccessful();

        $this->fakeSync(fn () => throw new \RuntimeException('something else broke'));
        $this->artisan('emails:sync-graph')->assertSuccessful();

        $this->assertCount(2, $this->sentEvents);
    }

    private function fakeSync(\Closure $onSync): void
    {
        $mock = $this->mock(GraphMailService::class);
        $mock->shouldReceive('configureMailbox')->andReturnNull();
        $mock->shouldReceive('processMessagesFromAllFolders')->andReturnUsing($onSync);
    }
}
