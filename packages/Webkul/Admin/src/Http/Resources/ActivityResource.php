<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     */
    public function toArray($request): array
    {
        // Using data_get avoids "Undefined property" when this resource wraps stdClass objects
        // (e.g., merged collections that include email-activity pseudo objects).
        $isRead = data_get($this->resource, 'is_read', 1);

        $data = [
            'id'                 => $this->id,
            'parent_id'          => $this->parent_id ?? null,
            'title'              => $this->title,
            'type'               => $this->type?->value ?? $this->type,
            'status'             => $this->renderStatus(),
            'type_label'         => $this->type?->name ?? null,
            'comment'            => $this->comment,
            'additional'         => is_array($this->resource->additional) ? $this->resource->additional : json_decode($this->resource->additional, true),
            'schedule_from'      => $this->schedule_from,
            'schedule_to'        => $this->schedule_to,
            'is_done'            => (int) $this->is_done,
            'is_read'            => $isRead,
            'user'               => $this->user ? new UserResource($this->user) : null,
            'user_id'            => $this->user_id ?? null,
            'lead_id'            => $this->lead_id ?? null,
            'sales_lead_id'      => $this->sales_lead_id ?? null,
            'order_id'           => $this->order_id ?? null,
            'files'              => is_array($this->files) ? $this->files : ActivityFileResource::collection($this->files),
            'location'           => $this->location,
            'linked_entity_type' => (isset($this->emailLinkedEntityType)) ? $this->emailLinkedEntityType : '',
            'activity_label'     => data_get($this->resource, 'activity_title', null),
            'activity_type'      => data_get($this->resource, 'activity_type', null),
            'folder_name'        => data_get($this->resource, 'folder_name', null),
            'publish_to_portal'  => $this->resource instanceof \Webkul\Activity\Models\Activity
                ? $this->resource->portalPersons->isNotEmpty()
                : (bool) data_get($this->resource, 'publish_to_portal', false),
            'is_published_to_portal' => $this->resource instanceof \Webkul\Activity\Models\Activity
                ? $this->resource->portalPersons->isNotEmpty()
                : (bool) data_get($this->resource, 'publish_to_portal', false),
            'portal_persons'     => $this->resource instanceof \Webkul\Activity\Models\Activity
                ? $this->resource->portalPersons->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()
                : [],
            'entity_source'      => data_get($this->resource, 'entity_source', null),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];

        $typeValue = $this->type?->value ?? $this->type;

        if (in_array($typeValue, ['task', 'call'], true) && $this->resource instanceof \Webkul\Activity\Models\Activity) {
            $allActions = $this->resource->actions()->with('creator')->latest()->get();

            if ($typeValue === 'task') {
                $data['task_comments'] = $allActions->where('type.value', 'notitie')->values()->map(fn ($a) => [
                    'date' => $a->created_at->locale('nl')->isoFormat('D MMM'),
                    'name' => $a->creator?->name ?? '',
                    'text' => $a->body,
                ])->values();
            }

            if ($typeValue === 'call') {
                $belstatusItems = $allActions->where('type.value', 'belstatus')->values();

                $summary = ['not_reachable' => 0, 'voicemail_left' => 0, 'spoken' => 0];
                foreach ($belstatusItems as $action) {
                    $key = is_string($action->call_status) ? $action->call_status : ($action->call_status?->value ?? null);
                    if ($key && isset($summary[$key])) {
                        $summary[$key]++;
                    }
                }

                $data['call_status_summary'] = $summary;
                $data['call_statuses'] = $belstatusItems->sortBy('created_at')->values()->map(fn ($a) => [
                    'id'           => $a->id,
                    'status'       => is_string($a->call_status) ? $a->call_status : ($a->call_status?->value ?? null),
                    'omschrijving' => $a->body,
                    'created_at'   => $a->created_at,
                    'creator'      => $a->creator ? ['name' => $a->creator->name] : null,
                ]);
            }

            // Unified actions list for timeline display in person/lead view
            $callStatusLabels = [
                'not_reachable' => 'Niet bereikt',
                'voicemail_left' => 'Voicemail',
                'spoken' => 'Gesproken',
            ];
            $actionItems = $allActions->map(function ($a) use ($callStatusLabels) {
                $callStatusKey = is_string($a->call_status) ? $a->call_status : ($a->call_status?->value ?? '');
                $statusLabel   = $callStatusLabels[$callStatusKey] ?? $callStatusKey;
                $body          = trim($a->body ?? '');

                return [
                    'type'      => $a->type?->value ?? $a->type,
                    'label'     => $a->type?->value === 'belstatus'
                        ? ($statusLabel . ($body !== '' ? ' — ' . mb_strimwidth($body, 0, 60, '…') : ''))
                        : mb_strimwidth($body, 0, 80, '…'),
                    'creator'   => $a->creator?->name ?? '',
                    'date'      => $a->created_at->locale('nl')->isoFormat('D MMM'),
                    'date_full' => $a->created_at->toIso8601String(),
                ];
            });

            $emailItems = $this->resource->emails()->latest()->get()->map(function ($e) {
                $from = is_array($e->from) ? ($e->from['name'] ?? $e->from['email'] ?? '') : ($e->from ?? '');

                return [
                    'type'      => 'mail',
                    'label'     => $e->subject ?? '(geen onderwerp)',
                    'creator'   => $from,
                    'date'      => $e->created_at->locale('nl')->isoFormat('D MMM'),
                    'date_full' => $e->created_at->toIso8601String(),
                ];
            });

            $data['actions'] = $actionItems->merge($emailItems)
                ->sortByDesc('date_full')
                ->values();
        }

        return $data;
    }

    private function renderStatus(): string
    {
        // Safely fetch status from the underlying resource (array|object|model)
        $status = data_get($this->resource, 'status');

        // If status is an enum/object with a label method
        if (is_object($status) && method_exists($status, 'label')) {
            return $status->label();
        }

        // If status is already a scalar/string value
        if (is_string($status) && $status !== '') {
            return $status;
        }

        // Fallback to is_done when status is not available
        $isDone = (bool) data_get($this->resource, 'is_done', false);

        return $isDone ? 'Afgerond' : 'Open';
    }
}
