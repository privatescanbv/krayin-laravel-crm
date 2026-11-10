<?php

namespace App\Http\Resources;

use App\Models\SalesLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Kaart voor het kliniekportaal. Personen dragen id, naam en geboortedatum (nullable);
 * `stage_entered_at` is het moment van de laatste stagewissel naar de huidige stage, null als dat niet bekend is.
 * Regio, diagnose en behandeladvies hebben nog geen CRM-bron en zijn bewust niet aanwezig.
 *
 * @property-read string|null $stage_entered_at Subquery-kolom uit ClinicSalesLeadController, geen echte kolom.
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
            'stage_entered_at'  => $this->stage_entered_at ? Carbon::parse($this->stage_entered_at)->toIso8601String() : null,
            'persons'           => $this->persons->map(fn ($person) => [
                'id'            => $person->id,
                'name'          => $person->name,
                // Raw column: Person's custom-attribute getAttribute() runs one extra query per person for a NULL column.
                'date_of_birth' => ($dob = $person->getRawOriginal('date_of_birth')) ? Carbon::parse($dob)->toDateString() : null,
            ])->values(),
        ];
    }
}
