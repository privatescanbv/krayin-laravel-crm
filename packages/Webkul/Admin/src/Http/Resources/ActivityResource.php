<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request): array
    {
        $data = [
            'id'              => $this->id,
            'parent_id'       => $this->parent_id ?? null,
            'title'           => $this->title,
            'type'            => $this->type?->value ?? $this->type,
            'status'          => $this->renderStatus(),
            'type_label'      => $this->type?->name ?? null,
            'comment'         => $this->comment,
            'additional'      => is_array($this->resource->additional) ? $this->resource->additional : json_decode($this->resource->additional, true),
            'schedule_from'   => $this->schedule_from,
            'schedule_to'     => $this->schedule_to,
            'is_done'         => (int) $this->is_done,
            'user'            => $this->user ? new UserResource($this->user) : null,
            'user_id'         => $this->user_id ?? null,
            'lead_id'         => $this->lead_id ?? null,
            'sales_lead_id'   => $this->sales_lead_id ?? null,
            // Emails: support both Eloquent models (relationLoaded) and stdClass/arrays used for email-activities
            'emails'          => (function () {
                try {
                    // If this is an Eloquent model and relation is loaded
                    if ($this->resource instanceof Model && $this->relationLoaded('emails')) {
                        return $this->emails->map(function ($email) {
                            return [
                                'id' => $email->id,
                                'subject' => $email->subject,
                                'created_at' => $email->created_at,
                            ];
                        });
                    }
                } catch (Throwable $e) {
                    // Fallback handled below
                }

                // If emails are present as a plain property/array
                if (is_object($this->resource) && isset($this->resource->emails) && is_iterable($this->resource->emails)) {
                    return collect($this->resource->emails)->map(function ($email) {
                        return [
                            'id' => is_object($email) ? ($email->id ?? null) : ($email['id'] ?? null),
                            'subject' => is_object($email) ? ($email->subject ?? null) : ($email['subject'] ?? null),
                            'created_at' => is_object($email) ? ($email->created_at ?? null) : ($email['created_at'] ?? null),
                        ];
                    });
                }

                return [];
            })(),
            'files'           => is_array($this->files) ? $this->files : ActivityFileResource::collection($this->files),
            'location'        => $this->location,
            'linked_entity_type' => (isset($this->emailLinkedEntityType)) ? $this->emailLinkedEntityType: '',
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];

        // Append call status summary/details for call activities
        $typeValue = $this->type?->value ?? $this->type;
        if ($typeValue === 'call') {
            $items = $this->whenLoaded('callStatuses') ? $this->callStatuses : $this->callStatuses()->with('creator')->get();

            $summary = [
                'not_reachable' => 0,
                'voicemail_left' => 0,
                'spoken' => 0,
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
                    'status' => is_string($cs->status) ? $cs->status : ($cs->status?->value ?? null),
                    'omschrijving' => $cs->omschrijving,
                    'created_at' => $cs->created_at,
                    'creator' => $cs->creator ? ['name' => $cs->creator->name] : null,
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
