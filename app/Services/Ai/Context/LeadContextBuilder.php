<?php

namespace App\Services\Ai\Context;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;

/**
 * Context for one lead: the running commercial thread, with earlier leads and
 * orders of the same patient as history.
 */
class LeadContextBuilder extends AiContextBuilder
{
    protected function resolveScope(Model $subject): AiContextScope
    {
        /** @var Lead $subject */
        $subject->loadMissing(['stage', 'source', 'type', 'persons']);

        return $this->scopeForPersons(
            personIds: $this->personIdsOf($subject),
            primaryLeadId: $subject->id,
        );
    }

    /**
     * A lead has at most one running order, so it gets its own block.
     */
    protected function currentOrderEntry(AiContextScope $scope): ?array
    {
        return $this->newestCurrentOrderEntry($scope);
    }

    /**
     * @return array<string, mixed>
     */
    protected function subjectEntry(Model $subject, AiContextScope $scope): array
    {
        /** @var Lead $subject */
        $source = $this->source(
            'lead',
            $subject->id,
            'Lead: '.$subject->name,
            $subject->updated_at ?? $subject->created_at,
            'Laatst gewijzigd',
            null,
            [
                'updated_at'  => $this->date($subject->updated_at),
                'description' => $subject->description,
                'stage'       => $subject->stage?->name,
                'lost_reason' => $subject->stage?->is_lost ? $subject->lost_reason?->label() : null,
            ],
        );

        $data = [
            'id'          => $subject->id,
            'name'        => $subject->name,
            'description' => $this->compactText($subject->description, 800),
            'stage'       => $subject->stage?->name,
            'source'      => $subject->source?->name,
            'type'        => $subject->type?->name,
            'updated_at'  => $this->date($subject->updated_at),
            'ref'         => $source['ref'] ?? null,
            '_source'     => $source,
        ];

        if ($subject->stage?->is_lost && $subject->lost_reason) {
            $data['lost_reason'] = $subject->lost_reason->label();
        }

        return $data;
    }
}
