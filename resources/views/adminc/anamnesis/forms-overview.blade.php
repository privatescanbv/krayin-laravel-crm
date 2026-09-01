@props([
    'entity',
    'entityType',
    'person',
    'effectiveAnamnesis' => null,
    'personHasPortalAccount' => false,
    'showDiagnosisAttach' => false,
    'salesLead' => null,
    'returnUrl' => null,
])
@php
    use App\Enums\FormType;
    use App\Services\Anamnesis\AnamnesisFormsOverviewBuilder;

    $builder = app(AnamnesisFormsOverviewBuilder::class);
    $overview = $builder->buildForPerson($entity, $person, $entityType);
    $contextType = $overview['context']['type'];
    $activeForms = $overview['active_forms'];
    $inactiveForms = $overview['inactive_forms'];
    $formCount = $overview['form_count'];
    $duplicateWarnings = $overview['duplicate_warnings'];

    $returnUrl = $returnUrl ?: strtok(url()->current(), '#') . '#anamnese';
    $defaultFormType = $effectiveAnamnesis
        ? FormType::defaultForAnamnesis($effectiveAnamnesis)->value
        : FormType::PrivateScan->value;

    $levelLabels = ['lead' => 'Lead', 'sales' => 'Sales', 'order' => 'Order'];
    $overviewId = 'forms-overview-' . $person->id . '-' . $entityType . '-' . $entity->id;

    $attachAnamnesisId = $effectiveAnamnesis?->id;
    $activeTypeValues = collect($activeForms)->merge($inactiveForms)->pluck('type_value');

    $missingDiagnosisTypes = $showDiagnosisAttach && $salesLead
        ? collect(FormType::diagnosisCases())->filter(fn (FormType $t) => ! $activeTypeValues->contains($t->value))
        : collect();

    $canAttachGvl = $attachAnamnesisId && $personHasPortalAccount;
    $canAttachDiagnosis = $showDiagnosisAttach && $salesLead && $personHasPortalAccount && $missingDiagnosisTypes->isNotEmpty();
    $showAttachControls = $canAttachGvl || $canAttachDiagnosis;

    $diagnosisAttachConfig = [];
    foreach ($missingDiagnosisTypes as $formType) {
        $salesTargetAnamnesis = \App\Models\Anamnesis::query()
            ->where('sales_id', $salesLead->id)
            ->where('person_id', $person->id)
            ->first() ?? new \App\Models\Anamnesis([
                'sales_id' => $salesLead->id,
                'person_id' => $person->id,
            ]);
        $diagDuplicate = $builder->activeDuplicateOnOtherLevel(
            $salesLead,
            $person,
            'sales',
            $salesTargetAnamnesis,
            $formType,
        );
        $diagnosisAttachConfig[$formType->value] = [
            'label' => $formType->label(),
            'duplicateMessage' => $diagDuplicate
                ? sprintf(
                    '%s staat al open op %s-niveau. Toch klaarzetten?',
                    $diagDuplicate['type_label'],
                    collect($diagDuplicate['levels'])->map(fn ($l) => $levelLabels[$l] ?? $l)->join(', '),
                )
                : null,
        ];
    }

    $selectableTypeValues = collect()
        ->when($canAttachGvl, fn ($c) => $c->merge(collect(FormType::manualCases())->map->value))
        ->when($canAttachDiagnosis, fn ($c) => $c->merge($missingDiagnosisTypes->map->value))
        ->values();
    $selectedFormType = $selectableTypeValues->contains($defaultFormType)
        ? $defaultFormType
        : ($selectableTypeValues->first() ?? $defaultFormType);
@endphp

<div
    id="{{ $overviewId }}"
    class="forms-overview space-y-2 rounded-lg border border-gray-200 bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-800/30"
    data-context="{{ $contextType }}"
    @if ($canAttachDiagnosis)
        data-diagnosis-attach-url="{{ route('admin.anamnesis.diagnosis-form.attach') }}"
        data-diagnosis-sales-id="{{ $salesLead->id }}"
        data-diagnosis-person-id="{{ $person->id }}"
        data-diagnosis-return-url="{{ $returnUrl }}"
        data-diagnosis-config='@json($diagnosisAttachConfig)'
    @endif
>
    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
        Formulieren{{ $formCount > 0 ? " ({$formCount})" : '' }}
    </h4>

    @if (! empty($duplicateWarnings))
        <div class="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            @foreach ($duplicateWarnings as $warning)
                <p>
                    {{ $warning['type_label'] }} staat ook open op
                    {{ collect($warning['levels'])->map(fn ($l) => $levelLabels[$l] ?? $l)->join(', ') }}.
                </p>
            @endforeach
        </div>
    @endif

    @unless ($personHasPortalAccount)
        <p class="text-xs text-amber-600 dark:text-amber-500">
            Maak eerst een patiëntportaal account aan om formulieren te koppelen.
        </p>
    @endunless

    @if ($activeForms !== [] || $inactiveForms !== [])
        @if ($activeForms !== [])
            <div class="space-y-1 overflow-x-auto">
                @foreach ($activeForms as $form)
                    @include('adminc.anamnesis.partials.forms-overview-row', ['form' => $form])
                @endforeach
            </div>
        @endif

        @if ($inactiveForms !== [])
            <details class="group">
                <summary class="cursor-pointer list-none text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <span class="inline-flex items-center gap-1">
                        <span class="icon-down-arrow text-[10px] transition-transform group-open:rotate-180"></span>
                        Oudere formulieren ({{ count($inactiveForms) }})
                    </span>
                </summary>
                <div class="mt-1 space-y-1 overflow-x-auto">
                    @foreach ($inactiveForms as $form)
                        @include('adminc.anamnesis.partials.forms-overview-row', ['form' => $form, 'muted' => true])
                    @endforeach
                </div>
            </details>
        @endif
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500">Geen formulieren gekoppeld.</p>
    @endif

    @if ($showAttachControls)
        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 pt-2 dark:border-gray-600">
            <select class="forms-overview-type-select rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @if ($canAttachGvl)
                    @foreach (FormType::manualCases() as $type)
                        @php
                            $typeActive = $builder->hasActiveFormOfType($overview, $type);
                        @endphp
                        <option value="{{ $type->value }}" @selected($type->value === $selectedFormType) @disabled($typeActive)>
                            {{ $type->label() }}{{ $typeActive ? ' (al actief)' : '' }}
                        </option>
                    @endforeach
                @endif
                @foreach ($missingDiagnosisTypes as $type)
                    <option value="{{ $type->value }}" @selected($type->value === $selectedFormType)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>
            <button
                type="button"
                class="primary-button forms-overview-action text-sm"
                data-action="attach"
                @if ($canAttachGvl)
                    data-gvl-attach-url="{{ route('admin.anamnesis.gvl-form.attach', $attachAnamnesisId) }}"
                @endif
            >
                <span class="forms-overview-action-label">Koppel formulier</span>
            </button>
        </div>
    @endif
</div>

@pushOnce('scripts', 'forms-overview')
<script type="module">
    const flash = (type, msg) => {
        const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
        emitter?.emit('add-flash', { type, message: msg });
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const parseJsonDataset = (value) => {
        if (! value) return {};
        try {
            return JSON.parse(value);
        } catch {
            return {};
        }
    };

    const isDiagnosisFormType = (overview, formType) => formType in parseJsonDataset(overview.dataset.diagnosisConfig);

    const updateAttachButtonLabel = (overview) => {
        const select = overview.querySelector('.forms-overview-type-select');
        const label = overview.querySelector('.forms-overview-action-label');
        if (! select || ! label) return;

        label.textContent = isDiagnosisFormType(overview, select.value) ? 'Klaarzetten' : 'Koppel formulier';
    };

    document.querySelectorAll('.forms-overview').forEach(updateAttachButtonLabel);

    document.addEventListener('change', (e) => {
        const select = e.target.closest('.forms-overview-type-select');
        if (! select) return;
        updateAttachButtonLabel(select.closest('.forms-overview'));
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.forms-overview-action');
        if (! btn || btn.disabled) return;

        const { action } = btn.dataset;
        const overview = btn.closest('.forms-overview');
        const formTypeSelect = overview?.querySelector('.forms-overview-type-select');
        const formType = formTypeSelect?.value;

        btn.disabled = true;
        const originalText = btn.querySelector('.forms-overview-action-label')?.textContent ?? btn.textContent;
        const labelEl = btn.querySelector('.forms-overview-action-label');
        if (labelEl) {
            labelEl.textContent = 'Bezig...';
        } else {
            btn.textContent = 'Bezig...';
        }

        const resetButton = () => {
            btn.disabled = false;
            if (labelEl) {
                labelEl.textContent = originalText;
            } else {
                btn.textContent = originalText;
            }
        };

        const doGvlAttach = async (url, force = false) => {
            const payload = { force };
            if (formTypeSelect && ! formTypeSelect.disabled) {
                payload.form_type = formTypeSelect.value;
            }

            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));

            if (res.status === 409 && data.requires_confirmation) {
                if (window.confirm(data.message || 'Formulier van hetzelfde type bestaat al. Toch koppelen?')) {
                    return doGvlAttach(url, true);
                }
                return false;
            }

            if (res.ok) {
                flash('success', data.message || 'Formulier gekoppeld.');
                setTimeout(() => location.reload(), 400);
                return true;
            }

            flash('error', data.message || 'Actie mislukt');
            return false;
        };

        const doDiagnosisAttach = async (force = false) => {
            const config = parseJsonDataset(overview.dataset.diagnosisConfig)[formType] ?? {};
            const duplicateMessage = config.duplicateMessage;

            if (! force && duplicateMessage) {
                if (! window.confirm(duplicateMessage)) {
                    return false;
                }

                return doDiagnosisAttach(true);
            }

            if (! force && ! window.confirm(`Er wordt een ${config.label ?? 'diagnoseformulier'} klaargezet in het patiëntenportaal voor deze patiënt.`)) {
                return false;
            }

            const body = new FormData();
            body.append('_token', csrf());
            body.append('sales_id', overview.dataset.diagnosisSalesId);
            body.append('person_id', overview.dataset.diagnosisPersonId);
            body.append('form_type', formType);
            body.append('return_url', overview.dataset.diagnosisReturnUrl);
            body.append('force', force ? '1' : '0');

            const res = await fetch(overview.dataset.diagnosisAttachUrl, {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (res.ok || res.redirected) {
                location.reload();
                return true;
            }

            flash('error', 'Diagnoseformulier klaarzetten is mislukt');
            return false;
        };

        try {
            if (action === 'attach') {
                let ok = false;

                if (isDiagnosisFormType(overview, formType)) {
                    ok = await doDiagnosisAttach(false);
                } else {
                    const url = btn.dataset.gvlAttachUrl;
                    if (! url) {
                        flash('error', 'Geen anamnese beschikbaar om dit formulier te koppelen.');
                        resetButton();
                        return;
                    }
                    ok = await doGvlAttach(url, false);
                }

                if (! ok) {
                    resetButton();
                }
                return;
            }

            if (action === 'detach') {
                const confirmMsg = btn.dataset.confirm;
                if (confirmMsg && ! window.confirm(confirmMsg)) {
                    resetButton();
                    return;
                }

                const res = await fetch(btn.dataset.url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok) {
                    flash('success', data.message || 'Formulier ontkoppeld.');
                    setTimeout(() => location.reload(), 400);
                    return;
                }

                flash('error', data.message || 'Actie mislukt');
            }
        } catch (err) {
            flash('error', err.message || 'Er is een fout opgetreden.');
        }

        resetButton();
    });
</script>
@endPushOnce
