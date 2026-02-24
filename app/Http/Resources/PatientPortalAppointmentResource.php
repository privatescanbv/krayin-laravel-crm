<?php

namespace App\Http\Resources;

use App\Models\Clinic;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

/**
 * Unified resource for patient portal appointment items.
 *
 * Handles both order-based and activity-based appointments.
 *
 * Expected resource payload shape:
 *   [
 *     'source'  => 'order' | 'activity',
 *     'sort_at' => Carbon,
 *     'payload' => [
 *       'clinic' => int|string|null,  // Clinic ID
 *       'person' => Person,
 *       'order'  => Order,            // when source = 'order'
 *       'activity' => Activity,       // when source = 'activity'
 *     ],
 *   ]
 */
class PatientPortalAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Person $person */
        $person = $this['payload']['person'];

        $clinic = $this['payload']['clinic']
            ? Clinic::with('address')->find($this['payload']['clinic'])
            : null;

        return match ($this['source']) {
            'order'    => $this->fromOrder($this['payload']['order'], $clinic, $person),
            'activity' => $this->fromActivity($this['payload']['activity'], $clinic, $person),
        };
    }

    private function fromOrder(Order $order, ?Clinic $clinic, Person $person): array
    {
        return [
            'id'              => 'order-'.$order->id,
            'patient_id'      => (string) $person->id,
            'practitioner_id' => null,
            'clinic_id'       => $clinic ? (string) $clinic->id : null,
            'clinic_ref'      => ClinicResource::make($clinic),
            'start_at'        => $order->first_examination_at?->toIso8601String(),
            'end_at'          => null,
            'timezone'        => config('app.timezone'),
            'is_remote'       => false,
            'remote_url'      => null,
            'created_at'      => $order->created_at->toIso8601String(),
            'updated_at'      => $order->updated_at?->toIso8601String(),
        ];
    }

    private function fromActivity(Activity $activity, ?Clinic $clinic, Person $person): array
    {
        return [
            'id'              => 'activity-'.$activity->id,
            'patient_id'      => (string) $person->id,
            'practitioner_id' => null,
            'clinic_id'       => $clinic ? (string) $clinic->id : null,
            'clinic_ref'      => ClinicResource::make($clinic),
            'start_at'        => $activity->schedule_from?->toIso8601String(),
            'end_at'          => $activity->schedule_to?->toIso8601String(),
            'timezone'        => config('app.timezone'),
            'is_remote'       => false,
            'remote_url'      => null,
            'created_at'      => $activity->created_at->toIso8601String(),
            'updated_at'      => $activity->updated_at?->toIso8601String(),
        ];
    }
}
