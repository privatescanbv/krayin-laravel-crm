@props([
    'anamnesis',
    'defaultFormType' => 'privatescan',
    'personHasPortalAccount' => false,
])
@php
    use App\Support\GvlFormLink;

    $gvlForms = $anamnesis->relationLoaded('gvlForms') ? $anamnesis->gvlForms : $anamnesis->gvlForms()->get();

    $statusLabels = [
        'new'       => ['Nieuw', 'text-gray-600'],
        'step1'     => ['Stap 1', 'text-yellow-600'],
        'step2'     => ['Stap 2', 'text-activity-note-text'],
        'step3'     => ['Stap 3', 'text-status-active-text'],
        'completed' => ['Voltooid', 'text-status-active-text'],
    ];
@endphp

<div class="space-y-2">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">GVL Formulieren</span>
    </div>

    @if ($gvlForms->isNotEmpty())
        <div class="space-y-1">
            @foreach ($gvlForms as $gvlForm)
                @php
                    $statusValue = $gvlForm->gvl_form_status?->value ?? 'Onbekend';
                    [$statusText, $statusColor] = $statusLabels[$statusValue] ?? ['Onbekend', 'text-gray-400'];
                    $openUrl = GvlFormLink::adminOpenUrl(
                        $gvlForm->gvl_form_link,
                        $anamnesis->person_id ? (int) $anamnesis->person_id : null,
                        (bool) $personHasPortalAccount
                    );
                    $detachUrl = route('admin.anamnesis.gvl-form.detach', [$anamnesis->id, $gvlForm->id]);
                @endphp
                <div class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-1 items-center gap-3 overflow-hidden text-sm">
                        @if ($gvlForm->gvl_form_type)
                            <span class="shrink-0 font-medium text-gray-600 dark:text-gray-400">{{ $gvlForm->gvl_form_type->label() }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                        @endif
                        <span class="{{ $statusColor }} shrink-0 font-medium">{{ $statusText }}</span>
                        @if ($openUrl)
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            @if ($personHasPortalAccount || $openUrl)
                                <a href="{{ $openUrl }}" target="_blank" class="truncate text-activity-note-text hover:underline">
                                    Formulier bekijken
                                </a>
                            @else
                                <span class="truncate text-gray-500">{{ $gvlForm->gvl_form_id }}</span>
                            @endif
                        @endif
                    </div>
                    <button
                        type="button"
                        class="gvl-action shrink-0 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        data-action="detach"
                        data-url="{{ $detachUrl }}"
                        data-confirm="Weet je zeker dat je dit GVL formulier wil ontkoppelen?"
                        title="Ontkoppel formulier"
                    >
                        Ontkoppel
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500">Geen GVL formulier gekoppeld.</p>
    @endif

    <!-- Add new GVL form -->
    @if (!$personHasPortalAccount)
        <p class="text-xs text-amber-600 dark:text-amber-500">
            <span class="icon-warning"></span>
            Maak eerst een patiëntportaal account aan voor deze persoon om een formulier te koppelen.
        </p>
    @endif
    <div class="flex items-center gap-2">
        <select
            class="gvl-form-type-select rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            title="Formulier type"
        >
            @foreach (\App\Enums\FormType::manualCases() as $type)
                <option
                    value="{{ $type->value }}"
                    @if ($type->value === $defaultFormType) selected @endif
                >{{ $type->label() }}</option>
            @endforeach
        </select>
        <button
            type="button"
            class="primary-button gvl-action"
            data-action="attach"
            data-url="{{ route('admin.anamnesis.gvl-form.attach', $anamnesis->id) }}"
            data-entity-type="anamnesis"
        >
            Koppel nieuw formulier
        </button>
    </div>
</div>

@pushOnce('scripts')
<script type="module">
    const flash = (type, msg) => {
        const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
        emitter?.emit('add-flash', { type, message: msg });
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.gvl-action');
        if (!btn || btn.disabled) return;

        const { action, url, confirm: confirmMsg } = btn.dataset;
        if (!url) return;

        if (confirmMsg && !window.confirm(confirmMsg)) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Bezig...';

        try {
            const isAttach = action === 'attach';
            let body = undefined;

            if (isAttach) {
                const formTypeSelect = btn.closest('div')?.querySelector('.gvl-form-type-select');
                const payload = {};
                if (formTypeSelect) payload.form_type = formTypeSelect.value;
                body = JSON.stringify(payload);
            }

            const res = await fetch(url, {
                method: isAttach ? 'POST' : 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                flash('success', data.message || (isAttach ? 'GVL formulier gekoppeld.' : 'GVL formulier ontkoppeld.'));
                setTimeout(() => location.reload(), 400);
            } else {
                throw new Error(data.message || 'Actie mislukt');
            }
        } catch (err) {
            flash('error', err.message || 'Er is een fout opgetreden.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
</script>
@endPushOnce
