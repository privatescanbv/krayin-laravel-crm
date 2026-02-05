<?php

namespace App\Http\Resources;

use App\Models\Clinic;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Contact\Models\Person;

class PaptientAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this['order'];

        /** @var Clinic|null $clinic */
        $clinic = Clinic::with('address')->find($this['clinic']);

        /** @var Person $person */
        $person = $this['person'];

        return [
            'id'              => 'order-'.$order->id,
            'patient_id'      => (string) $person->id,
            'practitioner_id' => null,
            'clinic_id'       => $clinic ? (string) $clinic->id : null,
            'clinic_ref'      => ClinicResource::make($clinic),
            'start_at'        => $order->first_examination_at?->toIso8601String(),
            'end_at'          => null,
            'timezone'        => config('app.timezone'),
            'is_remote'       => true,
            'remote_url'      => $order->remote_url,
            'created_at'      => $order->created_at->toIso8601String(),
            'updated_at'      => $order->updated_at?->toIso8601String(),
        ];
    }
}
