<?php

namespace App\Http\Resources;

use App\Services\AppointmentDetailService;
use App\Support\AppointmentReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eén regel van de afspraken van een patiënt op de sale-kaart. Verwacht een regel uit
 * {@see AppointmentDetailService::forPerson()}. `id` is de boeking (bruikbaar voor het afspraak-detail), null zonder
 * boeking. Nooit prijzen of interne velden.
 */
class ClinicSalesAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['item' => $item, 'booking' => $booking, 'status' => $status] = $this->resource;
        $type = $item->resolvedProductType();
        $clinic = $booking?->resource?->clinic;

        return [
            'id'            => $booking ? AppointmentReference::encode($item->order, $booking) : null,
            'order_item_id' => $item->id,
            'order_id'      => $item->order_id,
            'label'         => $item->getProductName() ?: 'Onbekend product',
            'type'          => $type ? ['id' => $type->id, 'label' => $type->name] : null,
            'status'        => $status,
            'start_at'      => $booking?->from?->toIso8601String(),
            'end_at'        => $booking?->to?->toIso8601String(),
            'expected_date' => $status === AppointmentDetailService::UNSCHEDULED ? $item->order->first_examination_at?->toDateString() : null,
            'clinic'        => $clinic ? [
                'id'      => $clinic->id,
                'label'   => $clinic->name,
                'address' => $clinic->visitAddress?->multiline_address,
            ] : null,
        ];
    }
}
