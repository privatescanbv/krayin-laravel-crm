<?php

namespace App\Services\Anamnesis;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Webkul\Contact\Models\Person;

class AnamnesisGvlFormReuseService
{
    public const AGE_WARNING_DAYS = 30;

    public function __construct(
        private readonly GvlAuditLogger $auditLogger,
    ) {}

    /**
     * Completed GVL-flow forms of this person that are not yet linked to the target anamnesis.
     *
     * @return list<array{
     *     id: int,
     *     gvl_form_id: string,
     *     type_label: string,
     *     long_label: string,
     *     completed_on: string,
     *     source_label: string,
     *     days_old: int,
     *     needs_age_warning: bool,
     *     age_warning: ?string
     * }>
     */
    public function candidatesForAnamnesis(Anamnesis $target): array
    {
        if ($target->person_id === null) {
            return [];
        }

        $alreadyLinkedIds = $target->gvlForms()
            ->whereNotNull('gvl_form_id')
            ->pluck('gvl_form_id')
            ->all();

        $forms = AnamnesisGvlForm::query()
            ->whereHas('anamnesis', fn ($q) => $q->where('person_id', $target->person_id))
            ->where('anamnesis_id', '!=', $target->id)
            ->where('gvl_form_status', FormStatus::Completed)
            ->where(function ($q) {
                $q->whereNull('gvl_form_type')
                    ->orWhereIn('gvl_form_type', FormType::gvlValues());
            })
            ->when($alreadyLinkedIds !== [], fn ($q) => $q->whereNotIn('gvl_form_id', $alreadyLinkedIds))
            ->with(['anamnesis.order', 'anamnesis.sales', 'anamnesis.lead'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        return $forms
            ->unique('gvl_form_id')
            ->values()
            ->map(fn (AnamnesisGvlForm $form) => $this->toCandidate($form))
            ->all();
    }

    public function reuseOnto(Anamnesis $target, int $sourceGvlFormRecordId): AnamnesisGvlForm
    {
        $source = AnamnesisGvlForm::with('anamnesis')->find($sourceGvlFormRecordId);

        if ($source === null) {
            throw new InvalidArgumentException('Bronformulier niet gevonden.');
        }

        if ($source->gvl_form_status !== FormStatus::Completed) {
            throw new InvalidArgumentException('Alleen een voltooide GVL kan worden overgenomen.');
        }

        if ($source->gvl_form_type !== null && ! $source->gvl_form_type->isGvlForm()) {
            throw new InvalidArgumentException('Dit formuliertype kan niet worden overgenomen.');
        }

        if ($source->anamnesis?->person_id !== $target->person_id) {
            throw new InvalidArgumentException('Formulier hoort bij een andere persoon.');
        }

        if (empty($source->gvl_form_id)) {
            throw new InvalidArgumentException('Bronformulier heeft geen portaal-id.');
        }

        $alreadyLinked = $target->gvlForms()
            ->where('gvl_form_id', $source->gvl_form_id)
            ->exists();

        if ($alreadyLinked) {
            throw new InvalidArgumentException('Dit formulier is al gekoppeld aan deze anamnese.');
        }

        $cloned = AnamnesisGvlForm::withoutEvents(function () use ($target, $source) {
            return AnamnesisGvlForm::create([
                'anamnesis_id'    => $target->id,
                'gvl_form_id'     => $source->gvl_form_id,
                'gvl_form_status' => FormStatus::Completed,
                'gvl_form_type'   => $source->gvl_form_type,
                'completed_at'    => $source->completed_at,
            ]);
        });

        $this->auditLogger->log($cloned, 'overgenomen');

        return $cloned;
    }

    /**
     * Move completed GVL-flow forms from an override anamnesis to the parent (sales or lead).
     * Incomplete forms are left in place for the caller to detach.
     */
    public function promoteCompletedGvlFormsToParent(Anamnesis $override): void
    {
        $parent = $this->parentAnamnesisForOverride($override);

        if ($parent === null) {
            return;
        }

        $override->load('gvlForms');

        foreach ($override->gvlForms as $form) {
            if (! $this->isCompletedGvlFlow($form)) {
                continue;
            }

            $alreadyOnParent = $parent->gvlForms()
                ->where('gvl_form_id', $form->gvl_form_id)
                ->exists();

            if ($alreadyOnParent || empty($form->gvl_form_id)) {
                $form->delete();

                continue;
            }

            $form->anamnesis_id = $parent->id;
            $form->save();
        }
    }

    public function hasOtherCrmReferences(AnamnesisGvlForm $gvlForm): bool
    {
        if (empty($gvlForm->gvl_form_id)) {
            return false;
        }

        return AnamnesisGvlForm::query()
            ->where('gvl_form_id', $gvlForm->gvl_form_id)
            ->where('id', '!=', $gvlForm->id)
            ->exists();
    }

    public function parentAnamnesisForOverride(Anamnesis $override): ?Anamnesis
    {
        $personId = (int) $override->person_id;

        if ($personId === 0) {
            return null;
        }

        if ($override->order_id) {
            $order = $override->order ?? Order::find($override->order_id);
            $salesId = $order?->sales_lead_id;
            $leadId = $order?->salesLead?->lead_id;

            return $this->findOrCreateParent($personId, $salesId, $leadId);
        }

        if ($override->sales_id) {
            $sales = $override->sales ?? SalesLead::find($override->sales_id);
            $leadId = $sales?->lead_id;

            return $this->findOrCreateParent($personId, null, $leadId);
        }

        return null;
    }

    private function findOrCreateParent(int $personId, ?int $salesId, ?int $leadId): ?Anamnesis
    {
        if ($salesId) {
            $salesParent = Anamnesis::query()
                ->where('sales_id', $salesId)
                ->where('person_id', $personId)
                ->whereNull('order_id')
                ->first();

            if ($salesParent) {
                return $salesParent;
            }
        }

        if ($leadId) {
            $leadParent = Anamnesis::query()
                ->where('lead_id', $leadId)
                ->where('person_id', $personId)
                ->whereNull('sales_id')
                ->whereNull('order_id')
                ->first();

            if ($leadParent) {
                return $leadParent;
            }
        }

        $personName = Person::find($personId)?->name ?? 'onbekend';
        $userId = auth()->guard('user')->id() ?? auth()->id() ?? 1;

        if ($salesId) {
            return Anamnesis::create([
                'id'         => (string) Str::uuid(),
                'name'       => 'Anamnese voor '.$personName,
                'sales_id'   => $salesId,
                'person_id'  => $personId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        if ($leadId) {
            return Anamnesis::create([
                'id'         => (string) Str::uuid(),
                'name'       => 'Anamnese voor '.$personName,
                'lead_id'    => $leadId,
                'person_id'  => $personId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return null;
    }

    /**
     * @return array{
     *     id: int,
     *     gvl_form_id: string,
     *     type_label: string,
     *     long_label: string,
     *     completed_on: string,
     *     source_label: string,
     *     days_old: int,
     *     needs_age_warning: bool,
     *     age_warning: ?string
     * }
     */
    private function toCandidate(AnamnesisGvlForm $form): array
    {
        $type = $form->gvl_form_type ?? FormType::PrivateScan;
        $completedAt = $form->completed_at ?? $form->created_at;
        $daysOld = $completedAt
            ? (int) round(abs($completedAt->diffInDays(now())))
            : 0;
        $needsAgeWarning = $daysOld >= self::AGE_WARNING_DAYS;
        $longLabel = $type === FormType::PrivateScan ? 'Gezondheidsvragenlijst' : $type->label();

        return [
            'id'                => $form->id,
            'gvl_form_id'       => (string) $form->gvl_form_id,
            'type_label'        => $type->label(),
            'long_label'        => $longLabel,
            'completed_on'      => $completedAt?->format('d-m-Y') ?? '—',
            'source_label'      => $this->sourceLabel($form->anamnesis),
            'days_old'          => $daysOld,
            'needs_age_warning' => $needsAgeWarning,
            'age_warning'       => $needsAgeWarning
                ? sprintf('Deze %s is %d dagen oud. Toch overnemen?', $longLabel, $daysOld)
                : null,
        ];
    }

    private function sourceLabel(?Anamnesis $anamnesis): string
    {
        if ($anamnesis === null) {
            return 'onbekend';
        }

        if ($anamnesis->order_id) {
            $order = $anamnesis->order;
            $ref = $order?->order_number ?: $order?->title ?: '#'.$anamnesis->order_id;

            return 'order '.$ref;
        }

        if ($anamnesis->sales_id) {
            $sales = $anamnesis->sales;
            $ref = $sales?->name ?: '#'.$anamnesis->sales_id;

            return 'sales '.$ref;
        }

        if ($anamnesis->lead_id) {
            $lead = $anamnesis->lead;
            $ref = $lead?->name ?: '#'.$anamnesis->lead_id;

            return 'lead '.$ref;
        }

        return 'onbekend';
    }

    private function isCompletedGvlFlow(AnamnesisGvlForm $form): bool
    {
        return $form->gvl_form_status === FormStatus::Completed
            && ($form->gvl_form_type === null || $form->gvl_form_type->isGvlForm());
    }
}
