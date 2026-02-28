<?php

namespace App\Services\DomainEvents;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Webkul\Lead\Models\Stage;

class DomainEventBuilder
{
    public static function pipelineStageChanged(
        string $aggregateType,
        Model $entity,
        ?int $oldStageId,
        int $newStageId,
    ): array {
        $oldStage = $oldStageId ? Stage::find($oldStageId) : null;
        $newStage = Stage::find($newStageId);

        return [
            'eventId'       => (string) Uuid::uuid7(),
            'timestamp'     => now()->toIso8601String(),
            'aggregateType' => $aggregateType,
            'aggregateId'   => $entity->getKey(),
            'eventType'     => 'PipelineStageChanged',
            'payload'       => [
                'oldStage' => $oldStage ? ['id' => $oldStage->id, 'code' => $oldStage->code, 'name' => $oldStage->name] : null,
                'newStage' => $newStage ? ['id' => $newStage->id, 'code' => $newStage->code, 'name' => $newStage->name] : null,
                'entity'   => $entity->toArray(),
            ],
        ];
    }
}
