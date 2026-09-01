<?php

namespace App\Services\Anamnesis;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use App\Support\GvlFormLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class AnamnesisFormsOverviewBuilder
{
    public function __construct(
        private AnamnesisGvlFormResolver $resolver,
    ) {}

    /**
     * @return array{
     *     context: array{type: string, id: int},
     *     levels: array{lead: ?array, sales: list<array>, orders: list<array>},
     *     form_types: list<FormType>,
     *     matrix: array<string, array{lead: ?array, sales: list<array>, orders: list<array>}>,
     *     duplicate_warnings: list<array{type: string, type_label: string, levels: list<string>}>,
     *     has_active_form: callable
     * }
     */
    /**
     * Group a person's anamnesis records into relationship chains for overview rendering.
     *
     * @return Collection<int, array{
     *     lead: ?Lead,
     *     entity: Lead|SalesLead|Order,
     *     entity_type: string,
     *     effective_anamnesis: Anamnesis,
     *     hernia_sales_lead: ?SalesLead
     * }>
     */
    public function chainsForPerson(Person $person): Collection
    {
        $anamneses = Anamnesis::query()
            ->where('person_id', $person->id)
            ->with(['gvlForms', 'lead', 'sales', 'order.salesLead'])
            ->orderByDesc('updated_at')
            ->get();

        if ($anamneses->isEmpty()) {
            return collect();
        }

        return $anamneses
            ->groupBy(fn (Anamnesis $a) => $this->chainKeyForAnamnesis($a))
            ->map(function (Collection $chainAnamneses) {
                $effective = $chainAnamneses->sortByDesc(fn (Anamnesis $a) => match (true) {
                    (bool) $a->order_id => 3,
                    (bool) $a->sales_id => 2,
                    default             => 1,
                })->first();

                $leadId = $this->chainLeadIdFromAnamnesis($effective);
                $lead = $leadId ? Lead::find($leadId) : null;

                $context = $this->resolveOverviewContext($lead, $effective);

                if ($context === null) {
                    // Orphaned anamnesis: its lead/sales/order was deleted. Skip the
                    // chain rather than crash the whole person view.
                    Log::warning('Anamnesis has no resolvable overview context; skipping chain', [
                        'anamnesis_id' => $effective->id,
                        'person_id'    => $effective->person_id,
                        'lead_id'      => $effective->lead_id,
                        'sales_id'     => $effective->sales_id,
                        'order_id'     => $effective->order_id,
                    ]);

                    return null;
                }

                [$entity, $entityType] = $context;

                $herniaSalesLead = $chainAnamneses
                    ->pluck('sales_id')
                    ->filter()
                    ->unique()
                    ->map(fn (int $id) => SalesLead::find($id))
                    ->first(fn (?SalesLead $sales) => $sales?->getDepartment()?->isHernia());

                return [
                    'lead'                => $lead,
                    'entity'              => $entity,
                    'entity_type'         => $entityType,
                    'effective_anamnesis' => $effective,
                    'hernia_sales_lead'   => $herniaSalesLead,
                ];
            })
            ->filter()
            ->values();
    }

    public function buildForPerson(Lead|SalesLead|Order $entity, Person $person, string $entityType): array
    {
        $allAnamneses = match ($entityType) {
            'order' => $this->resolver->loadForOrder($entity),
            'sales' => $this->resolver->loadForSales($entity),
            'lead'  => $this->resolver->loadForLead($entity),
            default => collect(),
        };

        $personAnamneses = $allAnamneses->where('person_id', $person->id);

        $chainLeadId = $this->chainLeadId($entity, $entityType);
        $chainSalesIds = $this->chainSalesIds($entity, $entityType);
        $chainOrderIds = $this->chainOrderIds($entity, $entityType);

        $leadSlot = $chainLeadId
            ? $this->buildLevelSlot(
                $personAnamneses->first(fn (Anamnesis $a) => $a->lead_id === $chainLeadId && ! $a->sales_id && ! $a->order_id),
                'lead',
                $chainLeadId,
                fn (int $id)      => route('admin.leads.view', $id).'#anamnese',
                fn (Anamnesis $a) => $a->lead?->name,
                $person,
            )
            : null;

        $salesSlots = collect($chainSalesIds)
            ->map(function (int $salesId) use ($personAnamneses, $person) {
                return $this->buildLevelSlot(
                    $personAnamneses->first(fn (Anamnesis $a) => $a->sales_id === $salesId && ! $a->order_id),
                    'sales',
                    $salesId,
                    fn (int $id)      => route('admin.sales-leads.view', $id).'#anamnese',
                    fn (Anamnesis $a) => $a->sales?->name,
                    $person,
                );
            })
            ->filter()
            ->values()
            ->all();

        $orderSlots = collect($chainOrderIds)
            ->map(function (int $orderId) use ($personAnamneses, $person) {
                return $this->buildLevelSlot(
                    $personAnamneses->first(fn (Anamnesis $a) => $a->order_id === $orderId),
                    'order',
                    $orderId,
                    fn (int $id)      => route('admin.orders.view', $id).'#anamnese',
                    fn (Anamnesis $a) => $a->order?->title ?: $a->order?->name,
                    $person,
                );
            })
            ->filter(function (?array $slot) use ($entityType) {
                if ($slot === null) {
                    return false;
                }

                if ($entityType === 'order') {
                    return true;
                }

                return ! empty($slot['forms']) || $slot['anamnesis_id'] !== null;
            })
            ->values()
            ->all();

        $matrix = $this->buildMatrix($leadSlot, $salesSlots, $orderSlots);
        $duplicateWarnings = $this->buildDuplicateWarnings($matrix);
        ['active' => $activeForms, 'inactive' => $inactiveForms, 'total_count' => $formCount] = $this->buildFormLists(
            $matrix,
            $entityType,
            (int) $entity->id,
            $chainOrderIds,
        );

        return [
            'context'             => ['type' => $entityType, 'id' => (int) $entity->id],
            'levels'              => [
                'lead'   => $leadSlot,
                'sales'  => $salesSlots,
                'orders' => $orderSlots,
            ],
            'form_types'          => FormType::cases(),
            'matrix'              => $matrix,
            'active_forms'        => $activeForms,
            'inactive_forms'      => $inactiveForms,
            'form_count'          => $formCount,
            'duplicate_warnings'  => $duplicateWarnings,
        ];
    }

    /**
     * Check if an active (non-completed) form of the given type exists anywhere in the chain.
     */
    public function hasActiveFormOfType(array $overview, FormType $type, ?string $excludeAnamnesisId = null): bool
    {
        return $this->allFormEntries($overview)
            ->contains(fn (array $entry) => $entry['type_value'] === $type->value
                && $entry['is_active']
                && ($excludeAnamnesisId === null || $entry['anamnesis_id'] !== $excludeAnamnesisId));
    }

    /**
     * Active form of same type on a different level than the target anamnesis.
     *
     * @return array{type: string, type_label: string, levels: list<string>}|null
     */
    public function activeDuplicateOnOtherLevel(
        Lead|SalesLead|Order $entity,
        Person $person,
        string $entityType,
        Anamnesis $targetAnamnesis,
        FormType $type,
    ): ?array {
        $overview = $this->buildForPerson($entity, $person, $entityType);
        $targetLevel = $targetAnamnesis->source_level;

        $otherLevels = $this->allFormEntries($overview)
            ->filter(fn (array $entry) => $entry['type_value'] === $type->value
                && $entry['is_active']
                && $entry['level'] !== $targetLevel)
            ->pluck('level')
            ->unique()
            ->values()
            ->all();

        if ($otherLevels === []) {
            return null;
        }

        return [
            'type'       => $type->value,
            'type_label' => $type->label(),
            'levels'     => $otherLevels,
        ];
    }

    /**
     * Resolve entity + type from an anamnesis record for duplicate checks.
     */
    public function contextForAnamnesis(Anamnesis $anamnesis): ?array
    {
        if ($anamnesis->order_id) {
            $order = Order::find($anamnesis->order_id);

            return $order ? ['entity' => $order, 'type' => 'order'] : null;
        }

        if ($anamnesis->sales_id) {
            $sales = SalesLead::find($anamnesis->sales_id);

            return $sales ? ['entity' => $sales, 'type' => 'sales'] : null;
        }

        if ($anamnesis->lead_id) {
            $lead = Lead::find($anamnesis->lead_id);

            return $lead ? ['entity' => $lead, 'type' => 'lead'] : null;
        }

        return null;
    }

    private function chainLeadId(Lead|SalesLead|Order $entity, string $entityType): ?int
    {
        return match ($entityType) {
            'lead'  => $entity->id,
            'sales' => $entity->lead_id,
            'order' => $entity->salesLead?->lead_id,
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    private function chainSalesIds(Lead|SalesLead|Order $entity, string $entityType): array
    {
        return match ($entityType) {
            'lead'  => SalesLead::query()->where('lead_id', $entity->id)->pluck('id')->all(),
            'sales' => [$entity->id],
            'order' => $entity->sales_lead_id ? [$entity->sales_lead_id] : [],
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    private function chainOrderIds(Lead|SalesLead|Order $entity, string $entityType): array
    {
        return match ($entityType) {
            'lead'  => Order::query()
                ->whereIn('sales_lead_id', SalesLead::query()->where('lead_id', $entity->id)->pluck('id'))
                ->pluck('id')
                ->all(),
            'sales' => $entity->orders()->pluck('id')->all(),
            'order' => [$entity->id],
            default => [],
        };
    }

    private function buildLevelSlot(
        ?Anamnesis $anamnesis,
        string $level,
        int $entityId,
        callable $urlFor,
        callable $labelFor,
        Person $person,
    ): ?array {
        $label = $anamnesis ? ($labelFor($anamnesis) ?: '#'.$entityId) : '#'.$entityId;

        $forms = $anamnesis
            ? $this->formsFromAnamnesis($anamnesis, $level, $entityId, $label, $urlFor($entityId), $person)
            : [];

        if ($anamnesis === null && $forms === []) {
            return [
                'entity_id'    => $entityId,
                'entity_label' => $label,
                'entity_url'   => $urlFor($entityId),
                'anamnesis_id' => null,
                'level'        => $level,
                'forms'        => [],
            ];
        }

        return [
            'entity_id'    => $entityId,
            'entity_label' => $label,
            'entity_url'   => $urlFor($entityId),
            'anamnesis_id' => $anamnesis?->id,
            'level'        => $level,
            'forms'        => $forms,
        ];
    }

    /**
     * @return list<array>
     */
    private function formsFromAnamnesis(
        Anamnesis $anamnesis,
        string $level,
        int $entityId,
        string $entityLabel,
        string $entityUrl,
        Person $person,
    ): array {
        $hasPortal = ! empty($person->keycloak_user_id);

        return ($anamnesis->relationLoaded('gvlForms') ? $anamnesis->gvlForms : $anamnesis->gvlForms()->get())
            ->map(fn (AnamnesisGvlForm $form) => $this->toFormEntry(
                $form,
                $anamnesis,
                $level,
                $entityId,
                $entityLabel,
                $entityUrl,
                $hasPortal,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     type: FormType,
     *     type_value: string,
     *     type_label: string,
     *     status: ?string,
     *     status_label: string,
     *     completed_at: ?string,
     *     created_at: ?string,
     *     open_url: ?string,
     *     anamnesis_id: string,
     *     level: string,
     *     entity_id: int,
     *     entity_label: string,
     *     entity_url: string,
     *     is_active: bool,
     *     detach_url: string
     * }
     */
    private function toFormEntry(
        AnamnesisGvlForm $form,
        Anamnesis $anamnesis,
        string $level,
        int $entityId,
        string $entityLabel,
        string $entityUrl,
        bool $hasPortal,
    ): array {
        $type = $form->gvl_form_type ?? FormType::PrivateScan;
        $status = $form->gvl_form_status;
        $statusValue = $status?->value ?? 'new';

        return [
            'id'             => $form->id,
            'type'           => $type,
            'type_value'     => $type->value,
            'type_label'     => $type->label(),
            'status'         => $statusValue,
            'status_label'   => $this->statusLabel($statusValue),
            'status_color'   => $this->statusColor($statusValue),
            'completed_at'   => $form->completed_at?->format('d-m-Y H:i'),
            'created_at'     => $form->created_at?->format('d-m-Y H:i'),
            'open_url'       => GvlFormLink::adminOpenUrl($form->gvl_form_link, (int) $anamnesis->person_id, $hasPortal),
            'anamnesis_id'   => $anamnesis->id,
            'level'          => $level,
            'entity_id'      => $entityId,
            'entity_label'   => $entityLabel,
            'entity_url'     => $entityUrl,
            'is_active'      => $status !== FormStatus::Completed,
            'detach_url'     => route('admin.anamnesis.gvl-form.detach', [$anamnesis->id, $form->id]),
        ];
    }

    /**
     * @param  list<array>  $salesSlots
     * @param  list<array>  $orderSlots
     * @return array<string, array{lead: ?array, sales: list<array>, orders: list<array>}>
     */
    private function buildMatrix(?array $leadSlot, array $salesSlots, array $orderSlots): array
    {
        $matrix = [];

        foreach (FormType::cases() as $type) {
            $matrix[$type->value] = [
                'lead'   => $this->findFormInSlot($leadSlot, $type),
                'sales'  => collect($salesSlots)->map(fn (?array $slot) => $this->findFormInSlot($slot, $type))->filter()->values()->all(),
                'orders' => collect($orderSlots)->map(fn (?array $slot) => $this->findFormInSlot($slot, $type))->filter()->values()->all(),
            ];
        }

        return $matrix;
    }

    /**
     * @param  array<string, array{lead: ?array, sales: list<array>, orders: list<array>}>  $matrix
     * @param  list<int>  $chainOrderIds
     * @return array{active: list<array>, inactive: list<array>, total_count: int}
     */
    private function buildFormLists(array $matrix, string $entityType, int $contextEntityId, array $chainOrderIds): array
    {
        $activeForms = [];
        $inactiveForms = [];

        foreach ($matrix as $row) {
            $allForType = $this->allFormsForTypeRow($row);

            if ($allForType === []) {
                continue;
            }

            $active = $this->resolveActiveFormForType($row, $entityType, $contextEntityId, $chainOrderIds);

            if ($active !== null) {
                $activeForms[] = $active;
            }

            $activeId = $active['id'] ?? null;

            foreach ($allForType as $form) {
                if ($activeId === null || $form['id'] !== $activeId) {
                    $inactiveForms[] = $form;
                }
            }
        }

        return [
            'active'      => $activeForms,
            'inactive'    => $inactiveForms,
            'total_count' => count($activeForms) + count($inactiveForms),
        ];
    }

    /**
     * @param  array{lead: ?array, sales: list<array>, orders: list<array>}  $row
     * @return list<array>
     */
    private function allFormsForTypeRow(array $row): array
    {
        $forms = [];

        if ($row['lead'] !== null) {
            $forms[] = $this->withCouplingLabel($row['lead']);
        }

        foreach ($row['sales'] as $form) {
            $forms[] = $this->withCouplingLabel($form);
        }

        foreach ($row['orders'] as $form) {
            $forms[] = $this->withCouplingLabel($form);
        }

        return $forms;
    }

    /**
     * @param  array{lead: ?array, sales: list<array>, orders: list<array>}  $row
     */
    private function resolveActiveFormForType(array $row, string $entityType, int $contextEntityId, array $chainOrderIds): ?array
    {
        if ($row['orders'] !== []) {
            if ($entityType === 'order') {
                $match = collect($row['orders'])->firstWhere('entity_id', $contextEntityId);

                if ($match) {
                    return $this->withCouplingLabel($match);
                }
            }

            foreach ($chainOrderIds as $orderId) {
                $match = collect($row['orders'])->firstWhere('entity_id', $orderId);

                if ($match) {
                    return $this->withCouplingLabel($match);
                }
            }

            return $this->withCouplingLabel($row['orders'][0]);
        }

        if ($row['sales'] !== []) {
            return $this->withCouplingLabel($row['sales'][0]);
        }

        if ($row['lead'] !== null) {
            return $this->withCouplingLabel($row['lead']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function withCouplingLabel(array $form): array
    {
        $levelLabels = ['lead' => 'Lead', 'sales' => 'Sales', 'order' => 'Order'];
        $form['coupling_label'] = ($levelLabels[$form['level']] ?? ucfirst($form['level'])).': '.$form['entity_label'];

        return $form;
    }

    private function findFormInSlot(?array $slot, FormType $type): ?array
    {
        if ($slot === null) {
            return null;
        }

        foreach ($slot['forms'] as $form) {
            if ($form['type_value'] === $type->value) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{lead: ?array, sales: list<array>, orders: list<array>}>  $matrix
     * @return list<array{type: string, type_label: string, levels: list<string>}>
     */
    private function buildDuplicateWarnings(array $matrix): array
    {
        $warnings = [];

        foreach ($matrix as $typeValue => $row) {
            $activeLevels = [];

            if (! empty($row['lead']) && ($row['lead']['is_active'] ?? false)) {
                $activeLevels[] = 'lead';
            }

            foreach ($row['sales'] as $form) {
                if ($form['is_active'] ?? false) {
                    $activeLevels[] = 'sales';
                }
            }

            foreach ($row['orders'] as $form) {
                if ($form['is_active'] ?? false) {
                    $activeLevels[] = 'order';
                }
            }

            $activeLevels = array_values(array_unique($activeLevels));

            if (count($activeLevels) > 1) {
                $type = FormType::fromValue($typeValue);
                $warnings[] = [
                    'type'       => $typeValue,
                    'type_label' => $type->label(),
                    'levels'     => $activeLevels,
                ];
            }
        }

        return $warnings;
    }

    private function allFormEntries(array $overview): Collection
    {
        $entries = collect();

        if ($overview['levels']['lead']) {
            $entries = $entries->merge($overview['levels']['lead']['forms']);
        }

        foreach ($overview['levels']['sales'] as $slot) {
            $entries = $entries->merge($slot['forms']);
        }

        foreach ($overview['levels']['orders'] as $slot) {
            $entries = $entries->merge($slot['forms']);
        }

        return $entries;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new'       => 'Nieuw',
            'step1'     => 'Stap 1',
            'step2'     => 'Stap 2',
            'step3'     => 'Stap 3',
            'completed' => 'Voltooid',
            default     => 'Onbekend',
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'new'       => 'text-gray-600',
            'step1'     => 'text-yellow-600',
            'step2'     => 'text-activity-note-text',
            'step3'     => 'text-status-active-text',
            'completed' => 'text-status-active-text',
            default     => 'text-gray-400',
        };
    }

    private function chainKeyForAnamnesis(Anamnesis $anamnesis): string
    {
        $leadId = $this->chainLeadIdFromAnamnesis($anamnesis);

        if ($leadId) {
            return 'lead-'.$leadId;
        }

        if ($anamnesis->sales_id) {
            return 'sales-'.$anamnesis->sales_id;
        }

        if ($anamnesis->order_id) {
            return 'order-'.$anamnesis->order_id;
        }

        return 'anamnesis-'.$anamnesis->id;
    }

    private function chainLeadIdFromAnamnesis(Anamnesis $anamnesis): ?int
    {
        if ($anamnesis->lead_id && ! $anamnesis->sales_id && ! $anamnesis->order_id) {
            return $anamnesis->lead_id;
        }

        if ($anamnesis->sales_id && $anamnesis->sales?->lead_id) {
            return $anamnesis->sales->lead_id;
        }

        if ($anamnesis->order_id && $anamnesis->order?->salesLead?->lead_id) {
            return $anamnesis->order->salesLead->lead_id;
        }

        return $anamnesis->lead_id;
    }

    /**
     * @return array{0: Lead|SalesLead|Order, 1: string}|null
     */
    private function resolveOverviewContext(?Lead $lead, Anamnesis $effective): ?array
    {
        if ($lead) {
            return [$lead, 'lead'];
        }

        if ($effective->sales_id && $effective->sales) {
            return [$effective->sales, 'sales'];
        }

        if ($effective->order_id && $effective->order) {
            return [$effective->order, 'order'];
        }

        return null;
    }
}
