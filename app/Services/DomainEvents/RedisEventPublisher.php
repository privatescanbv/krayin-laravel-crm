<?php

namespace App\Services\DomainEvents;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisEventPublisher
{
    private const CHANNEL      = 'crm.events';
    private const MAX_ATTEMPTS = 3;

    public function publish(array $event): bool
    {
        $json          = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                Redis::connection('events')->publish(self::CHANNEL, $json);

                Log::info('DomainEvent published to Redis', [
                    'eventId'       => $event['eventId'] ?? null,
                    'eventType'     => $event['eventType'] ?? null,
                    'aggregateType' => $event['aggregateType'] ?? null,
                    'aggregateId'   => $event['aggregateId'] ?? null,
                ]);

                return true;
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        Log::error('DomainEvent publish failed after ' . self::MAX_ATTEMPTS . ' attempts', [
            'eventId'       => $event['eventId'] ?? null,
            'eventType'     => $event['eventType'] ?? null,
            'aggregateType' => $event['aggregateType'] ?? null,
            'aggregateId'   => $event['aggregateId'] ?? null,
            'channel'       => self::CHANNEL,
            'error'         => $lastException?->getMessage(),
        ]);

        return false;
    }
}
