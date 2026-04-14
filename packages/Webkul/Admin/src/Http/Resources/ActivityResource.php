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
            'publish_to_portal'  => (bool) data_get($this->resource, 'publish_to_portal', false),
            'entity_source'      => data_get($this->resource, 'entity_source', null),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];

        // Append call status summary/details for call activities
        $typeValue = $this->type?->value ?? $this->type;
        if ($typeValue === 'call') {
            $items = $this->whenLoaded('callStatuses') ? $this->callStatuses : $this->callStatuses()->with('creator')->get();

            $summary = [
                'not_reachable'  => 0,
                'voicemail_left' => 0,
                'spoken'         => 0,
            ];

            foreach ($items as $cs) {
                $key = is_string($cs->status) ? $cs->status : ($cs->status?->value ?? null);
                if ($key && isset($summary[$key])) {
                    $summary[$key]++;
                }
            }

            $data['call_status_summary'] = $summary;
            $data['call_statuses'] = $items->sortBy('created_at')->values()->map(function ($cs) {
                return [
                    'id'           => $cs->id,
                    'status'       => is_string($cs->status) ? $cs->status : ($cs->status?->value ?? null),
                    'omschrijving' => $cs->omschrijving,
                    'created_at'   => $cs->created_at,
                    'creator'      => $cs->creator ? ['name' => $cs->creator->name] : null,
                ];
            });
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
