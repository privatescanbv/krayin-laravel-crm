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
@endphp

<div id="{{ $overviewId }}" class="forms-overview space-y-2 rounded-lg border border-gray-200 bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-800/30" data-context="{{ $contextType }}">
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

    @if ($missingDiagnosisTypes->isNotEmpty() && $personHasPortalAccount)
        <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-2 dark:border-gray-600">
            @foreach ($missingDiagnosisTypes as $formType)
                @php
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
                    $diagDuplicateMessage = $diagDuplicate
                        ? sprintf(
                            '%s staat al open op %s-niveau. Toch klaarzetten?',
                            $diagDuplicate['type_label'],
                            collect($diagDuplicate['levels'])->map(fn ($l) => $levelLabels[$l] ?? $l)->join(', '),
                        )
                        : null;
                @endphp
                @include('adminc.anamnesis.partials.forms-overview-diagnosis-attach', [
                    'formType' => $formType,
                    'salesLead' => $salesLead,
                    'person' => $person,
                    'returnUrl' => $returnUrl,
                    'personHasPortalAccount' => $personHasPortalAccount,
                    'duplicateMessage' => $diagDuplicateMessage,
                ])
            @endforeach
        </div>
    @endif

    @if ($attachAnamnesisId && $personHasPortalAccount)
        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 pt-2 dark:border-gray-600">
            <select class="forms-overview-type-select rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach (FormType::manualCases() as $type)
                    @php
                        $typeActive = $builder->hasActiveFormOfType($overview, $type);
                    @endphp
                    <option value="{{ $type->value }}" @if ($type->value === $defaultFormType) selected @endif @if ($typeActive) disabled @endif>
                        {{ $type->label() }}{{ $typeActive ? ' (al actief)' : '' }}
                    </option>
                @endforeach
            </select>
            <button
                type="button"
                class="primary-button forms-overview-action text-sm"
                data-action="attach"
                data-url="{{ route('admin.anamnesis.gvl-form.attach', $attachAnamnesisId) }}"
            >
                Koppel formulier
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

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.forms-overview-action');
        if (!btn || btn.disabled) return;

        const { action, url } = btn.dataset;
        if (!url) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Bezig...';

        const doAttach = async (force = false) => {
            const formTypeSelect = btn.closest('.forms-overview')?.querySelector('.forms-overview-type-select');
            const payload = { force };
            if (formTypeSelect && !formTypeSelect.disabled) {
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
                    return doAttach(true);
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

        try {
            if (action === 'attach') {
                const ok = await doAttach(false);
                if (!ok) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
                return;
            }

            if (action === 'detach') {
                const confirmMsg = btn.dataset.confirm;
                if (confirmMsg && !window.confirm(confirmMsg)) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    return;
                }

                const res = await fetch(url, {
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

        btn.disabled = false;
        btn.textContent = originalText;
    });
</script>
@endPushOnce
