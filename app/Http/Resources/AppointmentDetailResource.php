<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Services\AppointmentDetailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared afspraak-detail resource for both the clinic and patient portal
 * (tickets/portaal/02-appointment-detail.md). Never exposes prices or other internal/financial
 * fields. Expects the payload shape built by {@see AppointmentDetailService::build()}.
 */
class AppointmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productType = $this['type'];
        $salesLead = $this['sale'];
        $clinic = $this['clinic'];

        return [
            'id'       => $this['id'],
            'type'     => $productType ? ['id' => $productType->id, 'label' => $productType->name] : null,
            'start_at' => $this['start_at']?->toIso8601String(),
            'end_at'   => $this['end_at']?->toIso8601String(),
            'sale'     => $salesLead ? ['id' => $salesLead->id, 'label' => $salesLead->labelWithLeadSuffix()] : null,
            'clinic'   => $clinic ? [
                'id'      => $clinic->id,
                'label'   => $clinic->name,
                'address' => $clinic->visitAddress?->multiline_address,
            ] : null,
            'registration_note' => $this['registration_note'],
            'items'             => collect($this['items'])
                ->map(fn (OrderItem $item) => ['label' => $item->getDisplayLabel()])
                ->values()
                ->all(),
            // No document-type "preparation" <-> product-type link exists yet (tickets/portaal/02
            // scope note); always empty until that data model is built.
            'preparation' => [],
        ];
    }
}
