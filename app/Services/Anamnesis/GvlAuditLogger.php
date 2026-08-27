<?php

namespace App\Services\Anamnesis;

use App\Models\AnamnesisGvlForm;
use Webkul\Activity\Repositories\ActivityRepository;

/**
 * Writes a system activity ("wijzigingslogboek") whenever a GVL form is created,
 * removed, or completed, so employees can validate afterwards who did what and when.
 *
 * One FK is set, resolved in the same order CreateFormReviewTask uses:
 * active (open) order -> lead -> sales lead -> person.
 */
class GvlAuditLogger
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly AnamnesisOrderResolver $orderResolver,
    ) {}

    /**
     * @param  'aangemaakt'|'verwijderd'|'afgerond'  $verb
     */
    public function log(AnamnesisGvlForm $form, string $verb): void
    {
        $anamnesis = $form->anamnesis()->with('person')->first();

        if ($anamnesis === null) {
            return; // anamnesis itself already gone (cascade) — nothing to attach to
        }

        $typeLabel = $form->gvl_form_type?->label() ?? 'GVL-formulier';
        $personName = $anamnesis->person?->name ?? 'onbekend';

        $order = $this->orderResolver->findActiveOrderForAnamnesis($anamnesis);

        $fk = match (true) {
            $order !== null             => ['order_id' => $order->id],
            (bool) $anamnesis->lead_id  => ['lead_id' => $anamnesis->lead_id],
            (bool) $anamnesis->sales_id => ['sales_lead_id' => $anamnesis->sales_id],
            default                     => ['person_id' => $anamnesis->person_id],
        };

        $this->activityRepository->createSystem(array_merge($fk, [
            'title'      => sprintf('%s %s: %s', $typeLabel, $verb, $personName),
            'user_id'    => auth()->guard('user')->id(),
            'additional' => [
                'gvl_form_record_id' => $form->id,
                'gvl_form_id'        => $form->gvl_form_id,
                'gvl_form_type'      => $form->gvl_form_type?->value,
                'anamnesis_id'       => $anamnesis->id,
                'anamnesis_level'    => $anamnesis->order_id ? 'order' : ($anamnesis->sales_id ? 'sales' : 'lead'),
                'person_id'          => $anamnesis->person_id,
            ],
        ]));
    }
}
