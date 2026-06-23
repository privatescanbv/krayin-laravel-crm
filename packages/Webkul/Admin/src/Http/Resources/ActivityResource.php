<?php

namespace Webkul\Admin\Http\Resources;

use App\Enums\CallStatus;
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
            'completed_at'       => data_get($this->resource, 'completed_at', null),
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
                    $key = CallStatus::valueOf($action->call_status);
                    if ($key && isset($summary[$key])) {
                        $summary[$key]++;
                    }
                }

                $data['call_status_summary'] = $summary;
                $data['call_statuses'] = $belstatusItems->sortBy('created_at')->values()->map(fn ($a) => [
                    'id'           => $a->id,
                    'status'       => CallStatus::valueOf($a->call_status),
                    'omschrijving' => $a->body,
                    'created_at'   => $a->created_at,
                    'creator'      => $a->creator ? ['name' => $a->creator->name] : null,
                ]);
            }

            // Unified actions list for timeline display in person/lead view
            $actionItems = $allActions->map(function ($a) {
                $callStatusKey = CallStatus::valueOf($a->call_status) ?? '';
                $statusLabel   = CallStatus::labelFor($callStatusKey);
                $body          = trim($a->body ?? '');
                $typeValue     = $a->type?->value ?? $a->type;

                return [
                    'type'        => $typeValue,
                    'call_status' => $callStatusKey ?: null,
                    'label'       => $typeValue === 'belstatus'
                        ? ($statusLabel . ($body !== '' ? ' — ' . $body : ''))
                        : $body,
                    'creator'     => $a->creator?->name ?? '',
                    'date'        => $a->created_at->locale('nl')->isoFormat('D MMM HH:mm'),
                    'date_full'   => $a->created_at->toIso8601String(),
                ];
            });

            $emailItems = $this->resource->emails()->latest()->get()->map(function ($e) {
                $from = is_array($e->from) ? ($e->from['name'] ?? $e->from['email'] ?? '') : ($e->from ?? '');

                return [
                    'type'      => 'mail',
                    'label'     => $e->subject ?? '(geen onderwerp)',
                    'creator'   => $from,
                    'date'      => $e->created_at->locale('nl')->isoFormat('D MMM HH:mm'),
                    'date_full' => $e->created_at->toIso8601String(),
                ];
            });

            $data['actions'] = $actionItems->toBase()
                ->merge(collect($emailItems))
                ->sortByDesc('date_full')
                ->values()
                ->all();
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
