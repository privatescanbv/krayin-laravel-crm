<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            'id'              => $this->id,
            'parent_id'       => $this->parent_id ?? null,
            'title'           => $this->title,
            'type'            => $this->type?->value ?? $this->type,
            'type_label'      => $this->type?->name ?? null,
            'comment'         => $this->comment,
            'additional'      => is_array($this->resource->additional) ? $this->resource->additional : json_decode($this->resource->additional, true),
            'schedule_from'   => $this->schedule_from,
            'schedule_to'     => $this->schedule_to,
            'is_done'         => $this->is_done,
            'user'            => $this->user ? new UserResource($this->user) : null,
            'user_id'         => $this->user_id ?? null,
            'lead_id'         => $this->lead_id ?? null,
            'email_id'        => $this->email_id ?? null,
            'files'           => is_array($this->files) ? $this->files : ActivityFileResource::collection($this->files),
            'location'        => $this->location,
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
}
