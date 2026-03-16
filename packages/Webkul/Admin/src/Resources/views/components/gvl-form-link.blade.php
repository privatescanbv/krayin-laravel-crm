@props([
    'gvlFormLink' => null,
    'attachUrl' => null,
    'detachUrl' => null,
    'statusUrl' => null,
    'entityId' => null,
    'entityType' => 'order',
    'personId' => null,
    'personHasPortalAccount' => false,
    'leadId' => null,
])
@php
    $showNoPortalWarning = $personId && ! $personHasPortalAccount && $gvlFormLink;
    $useImpersonationLink = $personId
        && $personHasPortalAccount
        && bouncer()->hasPermission('contacts.persons.impersonate')
        && $gvlFormLink;
    $openFormUrl = $useImpersonationLink
        ? route('admin.contacts.persons.impersonate-and-open-form', $personId) . '?redirect=' . urlencode($gvlFormLink)
        : $gvlFormLink;
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
                <span class="gvl-status font-medium" data-url="{{ $statusUrl }}">Laden...</span>
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
            const body = isAttach && btn.dataset.entityType === 'person' && btn.dataset.personId
                ? JSON.stringify({ person_id: +btn.dataset.personId, lead_id: +btn.dataset.leadId })
                : undefined;

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

    // Load statuses - cache results in memory (survives Vue re-renders)
    const statusMap = {
        new: ['Nieuw', 'text-gray-600'],
        step1: ['Stap 1', 'text-yellow-600'],
        step2: ['Stap 2', 'text-activity-note-text'],
        step3: ['Stap 3', 'text-status-active-text'],
        completed: ['Voltooid', 'text-status-active-text'],
    };

    const statusCache = new Map(); // url -> {text, color}
    const pendingUrls = new Set(); // urls currently being fetched

    const loadStatuses = () => {
        document.querySelectorAll('.gvl-status[data-url]').forEach(async (el) => {
            const url = el.dataset.url;

            // If cached, apply immediately
            if (statusCache.has(url)) {
                const { text, color } = statusCache.get(url);
                el.textContent = text;
                el.className = `gvl-status font-medium ${color}`;
                return;
            }

            // If already fetching, skip
            if (pendingUrls.has(url)) return;
            pendingUrls.add(url);

            try {
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const status = (data.data?.status || data.data?.state || '').toLowerCase();
                const [text, color] = statusMap[status] || ['Onbekend', 'text-gray-400'];

                // Cache and apply
                statusCache.set(url, { text, color });
                el.textContent = text;
                el.className = `gvl-status font-medium ${color}`;
            } catch {
                statusCache.set(url, { text: 'Niet beschikbaar', color: 'text-gray-400' });
                el.textContent = 'Niet beschikbaar';
                el.className = 'gvl-status font-medium text-gray-400';
            } finally {
                pendingUrls.delete(url);
            }
        });
    };

    // Init when ready (with retries for Vue async rendering)
    const init = () => {
        loadStatuses();
        setTimeout(loadStatuses, 500);
        setTimeout(loadStatuses, 1500);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
</script>
@endPushOnce
