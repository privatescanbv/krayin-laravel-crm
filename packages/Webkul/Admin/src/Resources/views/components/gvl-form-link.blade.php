@props([
    'gvlFormLink' => null,
    'gvlFormStatus' => null,
    'gvlFormType' => null,
    'attachUrl' => null,
    'detachUrl' => null,
    'entityId' => null,
    'entityType' => 'order',
    'personId' => null,
    'personHasPortalAccount' => false,
    'leadId' => null,
    'defaultFormType' => 'privatescan',
])
@php
    use App\Support\GvlFormLink;

    $showNoPortalWarning = $personId && ! $personHasPortalAccount && $gvlFormLink;
    $openFormUrl = GvlFormLink::adminOpenUrl($gvlFormLink, $personId ? (int) $personId : null, (bool) $personHasPortalAccount);

    $statusLabels = [
        'new'       => ['Nieuw', 'text-gray-600'],
        'step1'     => ['Stap 1', 'text-yellow-600'],
        'step2'     => ['Stap 2', 'text-activity-note-text'],
        'step3'     => ['Stap 3', 'text-status-active-text'],
        'completed' => ['Voltooid', 'text-status-active-text'],
        'Onbekend' => ['Onbekend', 'text-red-600'],
    ];
    $statusValue = $gvlFormStatus->value ?? 'Onbekend';
    [$statusText, $statusColor] = $statusLabels[$statusValue] ?? ['Onbekend', 'text-gray-400'];
@endphp

<x-admin::form.control-group>
    <div class="flex items-center gap-2">
        <x-adminc::components.field
            type="text"
            name="gvl_form_link"
            label="GVL Formulier Link"
            value="{{ $gvlFormLink ?? '' }}"
            class="flex-1"
            readonly
        />
        @if($gvlFormLink)
            <button
                type="button"
                class="secondary-button gvl-action"
                title="Ontkoppel GVL formulier"
                data-action="detach"
                data-url="{{ $detachUrl }}"
                data-confirm="Weet je zeker dat je het GVL formulier wil ontkoppelen?"
            >
                Ontkoppel
            </button>
        @else
            <div class="flex items-center gap-2">
                <select
                    class="gvl-form-type-select rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    title="Formulier type"
                >
                    @foreach(\App\Enums\FormType::manualCases() as $type)
                        <option
                            value="{{ $type->value }}"
                            @if($type->value === $defaultFormType) selected @endif
                        >{{ $type->label() }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    class="primary-button gvl-action"
                    title="Koppel GVL formulier"
                    data-action="attach"
                    data-url="{{ $attachUrl }}"
                    data-entity-type="{{ $entityType }}"
                    @if($personId) data-person-id="{{ $personId }}" @endif
                    @if($leadId) data-lead-id="{{ $leadId }}" @endif
                >
                    Koppelen
                </button>
            </div>
        @endif
    </div>
    @if($gvlFormLink)
        <div class="mt-1 flex items-center gap-2 text-xs">
            @if($showNoPortalWarning)
                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-500" title="Maak een patiëntportaal account aan voor deze persoon om het formulier te kunnen openen">
                    <span class="icon-warning text-sm"></span>
                    Maak eerst een patiëntportaal account aan voor deze persoon.
                </span>
            @else
                <a href="{{ $openFormUrl }}" target="_blank" class="text-activity-note-text hover:underline">
                    Open formulier
                </a>
                <span class="text-gray-400">|</span>
                <span class="text-gray-500">Status: </span>
                <span class="font-medium {{ $statusColor }}">{{ $statusText }}</span>
                @if($gvlFormType)
                    <span class="text-gray-400">|</span>
                    <span class="text-gray-500">Type: </span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $gvlFormType->label() }}</span>
                @endif
            @endif
        </div>
    @endif
</x-admin::form.control-group>

@pushOnce('scripts')
<script type="module">
    const flash = (type, msg) => {
        const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
        emitter?.emit('add-flash', { type, message: msg });
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Handle attach/detach clicks
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

            // Read selected form type from the sibling select
            const formTypeSelect = btn.closest('div')?.querySelector('.gvl-form-type-select');
            const formType = formTypeSelect ? formTypeSelect.value : null;

            let body = undefined;
            if (isAttach) {
                const payload = {};
                if (formType) payload.form_type = formType;
                if (btn.dataset.entityType === 'person' && btn.dataset.personId) {
                    payload.person_id = +btn.dataset.personId;
                    if (btn.dataset.leadId) payload.lead_id = +btn.dataset.leadId;
                }
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
                flash('success', data.message || `GVL formulier ${isAttach ? 'gekoppeld' : 'ontkoppeld'}.`);
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
