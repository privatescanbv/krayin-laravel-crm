<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *   id: string,
 *   patient_id: string,
 *   practitioner_id?: string|null,
 *   clinic_id?: string|null,
 *   clinic_label?: string|null,
 *   start_at: string,
 *   end_at?: string|null,
 *   timezone?: string|null,
 *   is_remote: bool,
 *   remote_url?: string|null,
 *   created_at: string,
 *   updated_at?: string|null
 * } $resource
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // We intentionally use an array-based resource for the first implementation,
        // since "Appointment" is currently derived from Order data.
        return [
            'id'              => $this->resource['id'],
            'patient_id'      => $this->resource['patient_id'],
            'practitioner_id' => $this->resource['practitioner_id'] ?? null,
            'clinic_id'       => $this->resource['clinic_id'] ?? null,
            'clinic_label'    => $this->resource['clinic_label'] ?? null,
            'start_at'        => $this->resource['start_at'],
            'end_at'          => $this->resource['end_at'] ?? null,
            'timezone'        => $this->resource['timezone'] ?? null,
            'is_remote'       => (bool) ($this->resource['is_remote'] ?? false),
            'remote_url'      => $this->resource['remote_url'] ?? null,
            'created_at'      => $this->resource['created_at'],
            'updated_at'      => $this->resource['updated_at'] ?? null,
        ];
    }
}
