<?php

namespace Tests\Unit\DomainEvents;

use App\Services\DomainEvents\RedisEventPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class RedisEventPublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(): array
    {
        return [
            'eventId'       => 'test-event-id',
            'eventType'     => 'PipelineStageChanged',
            'aggregateType' => 'Lead',
            'aggregateId'   => 1,
        ];
    }

    /** @test */
    public function it_returns_true_and_logs_info_on_success(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('publish')->once()->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('events')
            ->once()
            ->andReturn($connection);

        Log::shouldReceive('info')
            ->once()
            ->with('DomainEvent published to Redis', Mockery::any());

        $publisher = new RedisEventPublisher();
        $result    = $publisher->publish($this->makeEvent());

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_and_logs_error_after_three_failed_attempts(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('publish')
            ->times(3)
            ->andThrow(new \RuntimeException('Connection refused'));

        Redis::shouldReceive('connection')
            ->with('events')
            ->times(3)
            ->andReturn($connection);

        Log::shouldReceive('error')
            ->once()
            ->with('DomainEvent publish failed after 3 attempts', Mockery::any());

        $publisher = new RedisEventPublisher();
        $result    = $publisher->publish($this->makeEvent());

        $this->assertFalse($result);
    }

    /** @test */
    public function it_retries_exactly_three_times_before_giving_up(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('publish')
            ->times(3)
            ->andThrow(new \RuntimeException('Timeout'));

        Redis::shouldReceive('connection')
            ->with('events')
            ->times(3)
            ->andReturn($connection);

        Log::shouldReceive('error')->once();

        $publisher = new RedisEventPublisher();
        $publisher->publish($this->makeEvent());

        // Mockery assertion that publish was called exactly 3 times is handled by ->times(3) above
        $this->assertTrue(true);
    }
}
