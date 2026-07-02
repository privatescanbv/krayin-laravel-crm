@php
use App\Enums\ActivityType;
use App\Enums\CallStatus;

$isCall = $activity->type === ActivityType::CALL;
$defaultTab = $isCall ? 'belstatus' : 'notitie';
$currentUserId = auth()->guard('user')->id();
$isActivityDone = (bool) $activity->is_done;

$statusConfig = [
    CallStatus::SPOKEN->value           => ['label' => CallStatus::SPOKEN->label(),           'color' => '#1f8a5b', 'bg' => '#dcf3e6', 'border' => '#a3d9b8', 'icon' => CallStatus::SPOKEN->icon()],
    CallStatus::NOT_REACHABLE->value    => ['label' => CallStatus::NOT_REACHABLE->label(),    'color' => '#e5484d', 'bg' => '#fce4e4', 'border' => '#f4a9aa', 'icon' => CallStatus::NOT_REACHABLE->icon()],
    CallStatus::VOICEMAIL_LEFT->value   => ['label' => CallStatus::VOICEMAIL_LEFT->label(),   'color' => '#3b7dd8', 'bg' => '#e2edfb', 'border' => '#93baf0', 'icon' => CallStatus::VOICEMAIL_LEFT->icon()],
];

// Build timeline: actions + emails, sorted descending
$timelineItems = collect();

foreach (($actions ?? collect()) as $action) {
    $timelineItems->push([
        'kind'        => $action->type->value,
        'id'          => $action->id,
        'ts'          => $action->created_at,
        'ts_fmt'      => $action->created_at?->format('d-m-Y H:i'),
        'body'        => $action->body,
        'call_status' => $action->call_status,
        'by'          => $action->creator?->name ?? '-',
        'can_delete'  => ! $isActivityDone && $action->created_by === $currentUserId,
    ]);
}

foreach ($activity->emails as $email) {
    $timelineItems->push([
        'kind'    => 'email',
        'ts'      => $email->created_at,
        'ts_fmt'  => $email->created_at?->format('d-m-Y H:i'),
        'id'      => $email->id,
        'subject' => $email->subject ?: 'Geen onderwerp',
        'preview' => \Illuminate\Support\Str::limit(strip_tags($email->reply ?? $email->body ?? ''), 80),
    ]);
}

$timelineItems = $timelineItems->sortByDesc('ts')->values();
$totalCount    = $timelineItems->count();
@endphp

{{-- ═══════════════════════════════════════════════════════════
     KAART 1: Actie toevoegen
     ═══════════════════════════════════════════════════════════ --}}
@if(!$isActivityDone)
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">

    {{-- ── Type selector ───────────────────────────────────────── --}}
    <div class="flex items-center gap-1 px-5 pt-4 pb-0" id="action-tabs">
        @foreach([
            ['key' => 'belstatus', 'label' => 'Belstatus',   'icon' => 'icon-call'],
            ['key' => 'notitie',   'label' => 'Notitie',     'icon' => 'icon-note'],
            ['key' => 'mail',      'label' => 'Mail',        'icon' => 'icon-mail'],
        ] as $tab)
            <button
                type="button"
                data-tab="{{ $tab['key'] }}"
                onclick="window.__actTab('{{ $tab['key'] }}')"
                class="act-tab inline-flex items-center gap-1.5 rounded-t-lg border border-b-0 px-4 py-2 text-sm font-semibold transition-colors
                    {{ $tab['key'] === $defaultTab
                        ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-gray-200 dark:border-gray-700 z-10'
                        : 'bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                <span class="{{ $tab['icon'] }} text-base"></span>
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    <div class="border-t border-gray-200 dark:border-gray-700 -mt-px"></div>

    {{-- ── Belstatus formulier ─────────────────────────────────── --}}
    <div id="act-panel-belstatus" class="{{ $defaultTab === 'belstatus' ? '' : 'hidden' }} px-5 py-4">
        <div class="flex flex-wrap gap-2 mb-4" id="act-chips">
            @foreach($statusConfig as $value => $cfg)
                <button
                    type="button"
                    data-status="{{ $value }}"
                    class="act-chip inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-all"
                    style="border-color: {{ $cfg['border'] }}; background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};"
                    onclick="window.__actChip(this)"
                >{{ $cfg['label'] }}</button>
            @endforeach
        </div>

        <textarea
            id="act-bel-body"
            rows="2"
            placeholder="Omschrijving (optioneel)…"
            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:border-gray-400 resize-none"
        ></textarea>

        @if($isCall)
            <div id="act-reschedule-wrap" class="mt-3">
                <label for="act-reschedule-days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Taak verplaatsen
                </label>
                <select
                    id="act-reschedule-days"
                    class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:outline-none focus:border-gray-400"
                >
                    <option value="">Geen verplaatsing</option>
                    @for($i = 1; $i <= 20; $i++)
                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'dag' : 'dagen' }}</option>
                    @endfor
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Verplaats de deadline met het geselecteerde aantal dagen
                </p>
            </div>
        @endif

        <div class="flex items-center justify-between mt-3 gap-3 flex-wrap">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer select-none shrink-0">
                <input type="checkbox" id="act-send-email" class="w-4 h-4 shrink-0 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                <span class="icon-mail text-base text-gray-500"></span>
                E-mail versturen na opslaan
            </label>
            <button
                type="button"
                id="act-bel-submit"
                onclick="window.__actSubmit('belstatus')"
                class="inline-flex items-center gap-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 text-sm font-semibold transition-colors"
            >
                <span class="icon-check text-base"></span>
                Belstatus toevoegen
            </button>
        </div>
    </div>

    {{-- ── Notitie formulier ───────────────────────────────────── --}}
    <div id="act-panel-notitie" class="{{ $defaultTab === 'notitie' ? '' : 'hidden' }} px-5 py-4">
        <div class="flex items-center justify-between px-0 pb-3">
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Toegevoegd door <strong class="text-gray-600 dark:text-gray-300">{{ auth()->guard('user')->user()?->name }}</strong>
            </span>
        </div>
        <textarea
            id="act-notitie-body"
            rows="3"
            placeholder="Typ een notitie…"
            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:border-gray-400 resize-none"
        ></textarea>
        <div class="flex justify-end mt-3">
            <button
                type="button"
                id="act-notitie-submit"
                onclick="window.__actSubmit('notitie')"
                class="inline-flex items-center gap-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 text-sm font-semibold transition-colors"
            >
                <span class="icon-check text-base"></span>
                Notitie toevoegen
            </button>
        </div>
    </div>

    {{-- ── Mail knop ───────────────────────────────────────────── --}}
    <div id="act-panel-mail" class="hidden px-5 py-4">
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Stuur een e-mail gekoppeld aan deze activiteit.</p>
        <button
            type="button"
            onclick="window.__actOpenMail()"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold transition-colors"
        >
            <span class="icon-mail text-base"></span>
            E-mail opstellen
        </button>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════
     KAART 2: Geschiedenis
     ═══════════════════════════════════════════════════════════ --}}
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex-wrap gap-3">
        <div class="flex items-center gap-2.5">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Geschiedenis</h2>
            <span id="act-count"
                  class="inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold text-xs min-w-[22px] h-[22px] px-1.5">
                {{ $totalCount }}
            </span>
        </div>
        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
            @foreach([
                ['key' => 'alles',     'label' => 'Alles'],
                ['key' => 'notitie',   'label' => 'Notities'],
                ['key' => 'belstatus', 'label' => 'Belstatus'],
                ['key' => 'email',     'label' => 'E-mails'],
            ] as $f)
                <button type="button" data-filter="{{ $f['key'] }}"
                        class="act-filter-tab rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors
                            {{ $f['key'] === 'alles' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}"
                        onclick="window.__actFilter('{{ $f['key'] }}')">{{ $f['label'] }}</button>
            @endforeach
        </div>
    </div>

    <div class="px-5 py-5" id="act-timeline">
        @forelse($timelineItems as $i => $item)
            @php $last = $i === $totalCount - 1; @endphp

            @if($item['kind'] === 'belstatus')
                @php
                    $callStatusKey = CallStatus::valueOf($item['call_status']);
                    $cfg = $statusConfig[$callStatusKey] ?? [
                        'label' => CallStatus::labelFor($callStatusKey),
                        'color' => '#555',
                        'bg' => '#eee',
                        'border' => '#ccc',
                        'icon' => CallStatus::iconFor($callStatusKey),
                    ];
                @endphp
                <div class="act-item flex gap-3 pb-5" data-kind="belstatus">
                    <div class="flex flex-col items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm"
                             style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};">
                            <span class="{{ $cfg['icon'] }} text-base"></span>
                        </div>
                        @if(!$last)<div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div>@endif
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $cfg['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $item['ts_fmt'] }}</span>
                                @if($item['can_delete'])
                                    <button type="button"
                                        onclick="window.__actDelete({{ $item['id'] }}, this)"
                                        class="p-1 text-gray-300 hover:text-red-500 transition-colors"
                                        title="Verwijderen">
                                        <span class="icon-delete text-sm"></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        @if($item['body'])
                            <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $item['body'] }}</div>
                        @endif
                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">Toegevoegd door {{ $item['by'] }}</div>
                    </div>
                </div>

            @elseif($item['kind'] === 'notitie')
                <div class="act-item flex gap-3 pb-5" data-kind="notitie">
                    <div class="flex flex-col items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                            <span class="icon-note text-base"></span>
                        </div>
                        @if(!$last)<div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div>@endif
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $item['by'] }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">notitie</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $item['ts_fmt'] }}</span>
                                @if($item['can_delete'])
                                    <button type="button"
                                        onclick="window.__actDelete({{ $item['id'] }}, this)"
                                        class="p-1 text-gray-300 hover:text-red-500 transition-colors"
                                        title="Verwijderen">
                                        <span class="icon-delete text-sm"></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $item['body'] }}</div>
                    </div>
                </div>

            @else
                {{-- email --}}
                <div class="act-item flex gap-3 pb-5" data-kind="email">
                    <div class="flex flex-col items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            <span class="icon-mail text-base"></span>
                        </div>
                        @if(!$last)<div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div>@endif
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $item['subject'] }}</span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">e-mail</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $item['ts_fmt'] }}</span>
                            </div>
                        </div>
                        @if($item['preview'])
                            <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $item['preview'] }}</div>
                        @endif
                        <div class="mt-1">
                            <a href="{{ route('admin.mail.view', ['route' => \Webkul\Email\Enums\EmailFolderEnum::INBOX->value, 'id' => $item['id']]) }}"
                               target="_blank"
                               class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                                Volledige e-mail openen <span class="icon-right-arrow text-xs"></span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">Nog geen geschiedenis.</div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
(function () {
    // ── Tab switching ────────────────────────────────────────────
    const defaultTab = '{{ $defaultTab }}';
    const statusConfig = @json($statusConfig);
    const spokenStatus = '{{ CallStatus::SPOKEN->value }}';
    const isCallActivity = @json($isCall);
    let selectedStatus = '{{ array_key_first($statusConfig) }}';

    window.__actTab = function (key) {
        document.querySelectorAll('.act-tab').forEach(function (btn) {
            const active = btn.dataset.tab === key;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('dark:bg-gray-900', active);
            btn.classList.toggle('text-gray-900', active);
            btn.classList.toggle('dark:text-white', active);
            btn.classList.toggle('border-gray-200', active);
            btn.classList.toggle('dark:border-gray-700', active);
            btn.classList.toggle('z-10', active);
            btn.classList.toggle('bg-gray-50', !active);
            btn.classList.toggle('dark:bg-gray-800', !active);
            btn.classList.toggle('text-gray-500', !active);
            btn.classList.toggle('dark:text-gray-400', !active);
            btn.classList.toggle('border-transparent', !active);
        });
        ['belstatus', 'notitie', 'mail'].forEach(function (p) {
            const el = document.getElementById('act-panel-' + p);
            if (el) el.classList.toggle('hidden', p !== key);
        });

        // Mail tab: directly open email dialog
        if (key === 'mail') {
            window.__actOpenMail();
            // Switch back to previous tab visually
            setTimeout(function () { window.__actTab(defaultTab); }, 100);
        }
    };

    // ── Chip selection ───────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const defaultChip = document.querySelector('[data-status="' + selectedStatus + '"]');
        if (defaultChip) __actApplyChip(defaultChip, true);
        __actApplyRescheduleDefaults();
    });

    window.__actChip = function (btn) {
        selectedStatus = btn.dataset.status;
        document.querySelectorAll('.act-chip').forEach(function (c) {
            __actApplyChip(c, c === btn);
        });
        __actApplyRescheduleDefaults();
    };

    function __actApplyRescheduleDefaults() {
        if (!isCallActivity) return;

        const wrap = document.getElementById('act-reschedule-wrap');
        const select = document.getElementById('act-reschedule-days');
        if (!wrap || !select) return;

        if (selectedStatus === spokenStatus) {
            select.value = '';
            select.disabled = true;
            wrap.classList.add('opacity-60');
            return;
        }

        select.disabled = false;
        wrap.classList.remove('opacity-60');
    }

    window.__actApplyChip = function (btn, active) {
        const cfg = statusConfig[btn.dataset.status];
        if (!cfg) return;
        if (active) {
            btn.style.background   = cfg.bg;
            btn.style.borderColor  = cfg.color;
            btn.style.color        = cfg.color;
            btn.style.boxShadow    = 'inset 0 0 0 1px ' + cfg.color;
        } else {
            btn.style.background  = '#fff';
            btn.style.borderColor = '#e5e7eb';
            btn.style.color       = '#6b7280';
            btn.style.boxShadow   = 'none';
        }
    };

    // ── Timeline filter ──────────────────────────────────────────
    window.__actFilter = function (f) {
        document.querySelectorAll('.act-filter-tab').forEach(function (tab) {
            const active = tab.dataset.filter === f;
            tab.classList.toggle('bg-white', active);
            tab.classList.toggle('dark:bg-gray-700', active);
            tab.classList.toggle('text-gray-900', active);
            tab.classList.toggle('dark:text-gray-100', active);
            tab.classList.toggle('shadow-sm', active);
            tab.classList.toggle('text-gray-500', !active);
            tab.classList.toggle('dark:text-gray-400', !active);
        });
        document.querySelectorAll('.act-item').forEach(function (item) {
            item.style.display = (f === 'alles' || item.dataset.kind === f) ? '' : 'none';
        });
    };

    // ── Submit ───────────────────────────────────────────────────
    const actStoreUrl = '{{ route('admin.activities.actions.store', $activity->id) }}';

    window.__actSubmit = async function (type) {
        const submitBtn = document.getElementById(type === 'belstatus' ? 'act-bel-submit' : 'act-notitie-submit');
        const origHtml  = submitBtn.innerHTML;

        let payload = { type };

        if (type === 'belstatus') {
            if (!selectedStatus) { alert('Selecteer een belstatus'); return; }
            payload.call_status  = selectedStatus;
            payload.body         = (document.getElementById('act-bel-body')?.value || '').trim() || null;
            payload.send_email   = document.getElementById('act-send-email')?.checked || false;

            if (isCallActivity) {
                const rescheduleEl = document.getElementById('act-reschedule-days');
                let rescheduleDays = rescheduleEl?.value || '';

                if (selectedStatus === spokenStatus) {
                    rescheduleDays = '';
                }

                if (rescheduleDays) {
                    payload.reschedule_days = parseInt(rescheduleDays, 10);
                }
            }
        } else {
            const body = (document.getElementById('act-notitie-body')?.value || '').trim();
            if (!body) { alert('Typ een notitie'); return; }
            payload.body = body;
        }

        submitBtn.disabled  = true;
        submitBtn.innerHTML = '<span class="icon-spinner animate-spin text-base"></span> Opslaan…';

        try {
            const res = await fetch(actStoreUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Opslaan mislukt');

            // Reset form
            if (type === 'belstatus') {
                document.getElementById('act-bel-body').value = '';
                document.getElementById('act-send-email').checked = false;
                __actApplyRescheduleDefaults();
            } else {
                document.getElementById('act-notitie-body').value = '';
            }

            if (data.send_email) {
                __actPrepend(data.data, type);
                window.dispatchEvent(new CustomEvent('open-email-dialog', {
                    detail: { defaultEmail: data.default_email || null, activityId: data.activity_id }
                }));
            } else if (type === 'belstatus' && data.data?.reschedule_days) {
                window.location.reload();
            } else {
                __actPrepend(data.data, type);
                if (window.app?._context?.app?.config?.globalProperties?.$emitter) {
                    window.app._context.app.config.globalProperties.$emitter.emit('add-flash', {
                        type: 'success', message: data.message || 'Opgeslagen'
                    });
                }
            }
        } catch (err) {
            alert(err.message || 'Er is een fout opgetreden');
        } finally {
            submitBtn.disabled  = false;
            submitBtn.innerHTML = origHtml;
        }
    };

    // ── Delete ───────────────────────────────────────────────────
    const actBaseUrl = '{{ url('admin/activities/' . $activity->id . '/actions') }}';

    window.__actDelete = async function (id, btn) {
        if (!confirm('Verwijderen?')) return;
        const item = btn.closest('.act-item');
        try {
            const res = await fetch(actBaseUrl + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Verwijderen mislukt');
            item?.remove();
            const countEl = document.getElementById('act-count');
            if (countEl) countEl.textContent = String(Math.max(0, (parseInt(countEl.textContent) || 0) - 1));
        } catch (err) {
            alert(err.message || 'Fout bij verwijderen');
        }
    };

    // ── Prepend new item to timeline ─────────────────────────────
    function __actPrepend(item, type) {
        const timeline = document.getElementById('act-timeline');
        if (!timeline) return;

        const emptyMsg = timeline.querySelector('.text-center');
        if (emptyMsg) emptyMsg.remove();

        const createdAt = item.created_at
            ? new Date(item.created_at).toLocaleString('nl-NL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : '';

        const myName = '{{ auth()->guard('user')->user()?->name }}';
        const div    = document.createElement('div');
        div.className     = 'act-item flex gap-3 pb-5';
        div.dataset.kind  = type;

        const deleteBtn = @if(!$isActivityDone) `<button type="button" onclick="window.__actDelete(${item.id}, this)"
            class="p-1 text-gray-300 hover:text-red-500 transition-colors" title="Verwijderen">
            <span class="icon-delete text-sm"></span></button>` @else '' @endif;

        if (type === 'belstatus') {
            const cfg = statusConfig[item.call_status] || { label: item.call_status, color: '#555', bg: '#eee', icon: 'icon-call' };
            div.innerHTML = `
                <div class="flex flex-col items-center">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm"
                         style="background:${cfg.bg};color:${cfg.color};">
                        <span class="${cfg.icon} text-base"></span>
                    </div>
                    <div class="w-px flex-1 bg-gray-200 mt-1.5 mb-[-8px]"></div>
                </div>
                <div class="flex-1 min-w-0 pt-1">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-sm text-gray-900">${cfg.label}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-400">${createdAt}</span>
                            ${deleteBtn}
                        </div>
                    </div>
                    ${item.body ? `<div class="mt-1.5 text-sm text-gray-600 leading-relaxed">${item.body}</div>` : ''}
                    <div class="mt-1 text-xs text-gray-400">Toegevoegd door ${myName}</div>
                </div>`;
        } else {
            div.innerHTML = `
                <div class="flex flex-col items-center">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <span class="icon-note text-base"></span>
                    </div>
                    <div class="w-px flex-1 bg-gray-200 mt-1.5 mb-[-8px]"></div>
                </div>
                <div class="flex-1 min-w-0 pt-1">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-sm text-gray-900">${myName}</span>
                            <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5 bg-amber-50 text-amber-600">notitie</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-400">${createdAt}</span>
                            ${deleteBtn}
                        </div>
                    </div>
                    <div class="mt-1.5 text-sm text-gray-600 leading-relaxed whitespace-pre-line">${item.body || ''}</div>
                </div>`;
        }

        timeline.insertBefore(div, timeline.firstChild);

        const countEl = document.getElementById('act-count');
        if (countEl) countEl.textContent = String((parseInt(countEl.textContent) || 0) + 1);
    }

    // ── Open mail dialog ─────────────────────────────────────────
    window.__actOpenMail = function () {
        window.dispatchEvent(new CustomEvent('open-email-dialog', {
            detail: { defaultEmail: null, activityId: {{ $activity->id }} }
        }));
    };
})();
</script>
@endpush
