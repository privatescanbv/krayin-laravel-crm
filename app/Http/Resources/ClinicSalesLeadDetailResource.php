<?php

namespace App\Http\Resources;

use App\Models\SalesLead;
use App\Services\AppointmentDetailService;
use App\Services\PlainText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Patiëntkaart van één sale voor het kliniekportaal: de kaartvelden plus gewhitelist detail.
 * Alleen CRM-bronnen die bestaan; geen diagnose, regio, behandeladvies, adressen of financiële gegevens.
 * Verwacht persons, stage, doctor en assessmentOutcome eager-loaded en `withStageEnteredAt()`.
 * `persons[].counts`: ingeplande en nog niet ingeplande afspraken per patiënt; de regels zelf staan op
 * `GET clinic/{id}/sales-leads/{saleId}/appointments/{personId}`.
 *
 * @mixin SalesLead
 */
class ClinicSalesLeadDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $card = (new ClinicSalesLeadResource($this->resource))->toArray($request);
        $appointmentCounts = app(AppointmentDetailService::class)->countsPerPerson($this->resource);

        return [
            ...$card,
            'persons' => $this->persons->map(fn ($person) => [
                'id'            => $person->id,
                'name'          => $person->name,
                // Raw columns: the custom-attribute getAttribute() runs one extra query per person for a NULL column.
                'first_name'    => $person->getRawOriginal('first_name'),
                'last_name'     => $person->getRawOriginal('last_name'),
                'date_of_birth' => ($dob = $person->getRawOriginal('date_of_birth')) ? Carbon::parse($dob)->toDateString() : null,
                'counts'        => $appointmentCounts[$person->id] ?? ['scheduled' => 0, 'unscheduled' => 0],
            ])->values(),
            'stage'                        => $this->stage ? new ClinicSalesStageResource($this->stage) : null,
            'doctor'                       => $this->doctor ? ['id' => $this->doctor->id, 'name' => $this->doctor->name] : null,
            'description'                  => PlainText::fromHtml($this->description),
            // Unknown label (code without row): keep the code, never invent a label.
            'assessment_outcome'           => $this->assessment_outcome
                ? ['code' => $this->assessment_outcome, 'label' => $this->assessmentOutcome?->label]
                : null,
            'additional_research_required' => (bool) $this->additional_research_required,
            'counts'                       => [
                'files'        => $this->clinicPortalFiles()->count(),
                'chat'         => (int) $this->team_chat_messages_count,
            ],
        ];
    }
}
