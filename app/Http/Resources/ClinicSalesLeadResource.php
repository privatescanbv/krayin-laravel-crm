<?php

namespace App\Http\Resources;

use App\Models\SalesLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimale kaart voor het kliniekportaal; meer persoonsgegevens komen via een aparte person-API.
 *
 * @mixin SalesLead
 */
class ClinicSalesLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'closed_at'         => $this->closed_at?->toIso8601String(),
            'persons'           => $this->persons->map(fn ($person) => ['id' => $person->id, 'name' => $person->name])->values(),
        ];
    }
}
