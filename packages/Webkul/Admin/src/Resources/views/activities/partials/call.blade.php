@php
use App\Enums\CallStatus;

// Build the combined timeline: call statuses + emails, sorted by timestamp desc
$timelineItems = collect();

$currentUserId = auth()->guard('user')->id();
foreach (($callStatuses ?? []) as $cs) {
    $timelineItems->push([
        'kind'       => 'belstatus',
        'id'         => $cs->id,
        'ts'         => $cs->created_at,
        'ts_fmt'     => $cs->created_at?->format('d-m-Y H:i'),
        'status'     => $cs->status,
        'note'       => $cs->omschrijving,
        'by'         => $cs->creator?->name ?? '-',
        'can_delete' => $cs->created_by === $currentUserId,
    ]);
}

foreach ($activity->emails as $email) {
    $timelineItems->push([
        'kind'      => 'email',
        'ts'        => $email->created_at,
        'ts_fmt'    => $email->created_at?->format('d-m-Y H:i'),
        'id'        => $email->id,
        'subject'   => $email->subject ?: 'Geen onderwerp',
        'preview'   => \Illuminate\Support\Str::limit(strip_tags($email->reply ?? $email->body ?? ''), 80),
        'is_read'   => (int)($email->is_read ?? 1),
    ]);
}

$timelineItems = $timelineItems->sortByDesc('ts')->values();
$totalCount = $timelineItems->count();

// Status chip config — matches design
$statusConfig = [
    CallStatus::SPOKEN->value           => ['label' => CallStatus::SPOKEN->label(),           'color' => '#1f8a5b', 'bg' => '#dcf3e6', 'border' => '#a3d9b8'],
    CallStatus::NOT_REACHABLE->value    => ['label' => CallStatus::NOT_REACHABLE->label(),    'color' => '#e5484d', 'bg' => '#fce4e4', 'border' => '#f4a9aa'],
    CallStatus::VOICEMAIL_LEFT->value   => ['label' => CallStatus::VOICEMAIL_LEFT->label(),   'color' => '#3b7dd8', 'bg' => '#e2edfb', 'border' => '#93baf0'],
    CallStatus::WORDT_TERUGGEBELD->value => ['label' => CallStatus::WORDT_TERUGGEBELD->label(), 'color' => '#e0962f', 'bg' => '#fbeed3', 'border' => '#f2c97a'],
    CallStatus::AFSPRAAK_GEMAAKT->value  => ['label' => CallStatus::AFSPRAAK_GEMAAKT->label(),  'color' => '#1f5f6b', 'bg' => '#d9eef0', 'border' => '#7dc4cd'],
];
@endphp

{{-- ═══════════════════════════════════════════════════════════
     KAART 1: Belstatus toevoegen
     ═══════════════════════════════════════════════════════════ --}}
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Belstatus toevoegen</h2>
        <span class="text-xs text-gray-400 dark:text-gray-500">
            Toegevoegd door <strong class="text-gray-600 dark:text-gray-300">{{ auth()->guard('user')->user()?->name }}</strong>
        </span>
    </div>

    <div class="px-5 py-4">
        {{-- Status chips --}}
        <div class="flex flex-wrap gap-2 mb-4" id="cs-chips">
            @foreach($statusConfig as $value => $cfg)
                <button
                    type="button"
                    data-status="{{ $value }}"
                    class="cs-chip inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-all"
                    style="border-color: {{ $cfg['border'] }}; background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};"
                    onclick="window.__csSelectChip(this)"
                >
                    {{ $cfg['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Notitie --}}
        <textarea
            id="cs-note"
            rows="2"
            placeholder="Notitie bij dit belmoment (optioneel)…"
            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:border-gray-400 resize-none"
        ></textarea>

        {{-- Footer: checkbox + submit --}}
        <div class="flex items-center justify-between mt-3 gap-3 flex-wrap">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer select-none shrink-0">
                <input type="checkbox" id="cs-send-email" class="w-4 h-4 shrink-0 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                <span class="icon-mail text-base text-gray-500"></span>
                E-mail versturen na opslaan
            </label>
            <button
                type="button"
                id="cs-submit"
                onclick="window.__csSubmit()"
                class="inline-flex items-center gap-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 text-sm font-semibold transition-colors"
            >
                <span class="icon-check text-base"></span>
                Belstatus toevoegen
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     KAART 2: History
     ═══════════════════════════════════════════════════════════ --}}
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex-wrap gap-3">
        <div class="flex items-center gap-2.5">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Geschiedenis</h2>
            <span id="cs-history-count"
                  class="inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold text-xs min-w-[22px] h-[22px] px-1.5">
                {{ $totalCount }}
            </span>
        </div>
        {{-- Filter tabs --}}
        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
            <button type="button" data-filter="alles"
                    class="cs-filter-tab rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm"
                    onclick="window.__csFilter('alles')">Alles</button>
            <button type="button" data-filter="bel"
                    class="cs-filter-tab rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors text-gray-500 dark:text-gray-400"
                    onclick="window.__csFilter('bel')">Belstatus</button>
            <button type="button" data-filter="email"
                    class="cs-filter-tab rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors text-gray-500 dark:text-gray-400"
                    onclick="window.__csFilter('email')">E-mails</button>
        </div>
    </div>

    <div class="px-5 py-5" id="cs-timeline">
        @forelse($timelineItems as $i => $item)
            @php $last = $i === $totalCount - 1; @endphp
            @if($item['kind'] === 'belstatus')
                @php
                    $cfg = $statusConfig[$item['status']->value] ?? ['label' => $item['status']->label(), 'color' => '#555', 'bg' => '#eee', 'border' => '#ccc'];
                @endphp
                <div class="cs-item flex gap-3 pb-5" data-kind="bel">
                    <div class="flex flex-col items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm"
                             style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};">
                            <span class="icon-phone text-base"></span>
                        </div>
                        @if(!$last) <div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div> @endif
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $cfg['label'] }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5"
                                      style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};">belstatus</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $item['ts_fmt'] }}</span>
                                @if($item['can_delete'])
                                    <button type="button"
                                        onclick="window.__csDeleteBelstatus({{ $item['id'] }}, this)"
                                        class="p-1 text-gray-300 hover:text-red-500 transition-colors"
                                        title="Verwijderen">
                                        <span class="icon-delete text-sm"></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        @if($item['note'])
                            <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $item['note'] }}</div>
                        @endif
                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">Toegevoegd door {{ $item['by'] }}</div>
                    </div>
                </div>
            @else
                <div class="cs-item flex gap-3 pb-5" data-kind="email">
                    <div class="flex flex-col items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-sm">
                            <span class="icon-mail text-base"></span>
                        </div>
                        @if(!$last) <div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div> @endif
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                    {{ $item['subject'] }}
                                    @if($item['is_read'] === 0)
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500 align-middle ml-0.5"></span>
                                    @endif
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">inkomend · e-mail</span>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ $item['ts_fmt'] }}</span>
                        </div>
                        @if($item['preview'])
                            <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $item['preview'] }}</div>
                        @endif
                        <div class="mt-1">
                            <a href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $item['id']]) }}"
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
    // ── Chip selection ──────────────────────────────────────────
    const statusConfig = @json($statusConfig);
    let selectedStatus = '{{ array_key_first($statusConfig) }}';

    // Highlight default chip on load
    document.addEventListener('DOMContentLoaded', function () {
        const defaultChip = document.querySelector('[data-status="' + selectedStatus + '"]');
        if (defaultChip) __csApplyChipStyle(defaultChip, true);
    });

    window.__csSelectChip = function (btn) {
        selectedStatus = btn.dataset.status;
        document.querySelectorAll('.cs-chip').forEach(function (c) {
            __csApplyChipStyle(c, c === btn);
        });
    };

    window.__csApplyChipStyle = function (btn, active) {
        const cfg = statusConfig[btn.dataset.status];
        if (!cfg) return;
        if (active) {
            btn.style.background = cfg.bg;
            btn.style.borderColor = cfg.color;
            btn.style.color = cfg.color;
            btn.style.boxShadow = 'inset 0 0 0 1px ' + cfg.color;
        } else {
            btn.style.background = '#fff';
            btn.style.borderColor = '#e5e7eb';
            btn.style.color = '#6b7280';
            btn.style.boxShadow = 'none';
        }
    };

    // ── Filter tabs ─────────────────────────────────────────────
    window.__csFilter = function (f) {
        document.querySelectorAll('.cs-filter-tab').forEach(function (tab) {
            const isActive = tab.dataset.filter === f;
            tab.classList.toggle('bg-white', isActive);
            tab.classList.toggle('dark:bg-gray-700', isActive);
            tab.classList.toggle('text-gray-900', isActive);
            tab.classList.toggle('dark:text-gray-100', isActive);
            tab.classList.toggle('shadow-sm', isActive);
            tab.classList.toggle('text-gray-500', !isActive);
            tab.classList.toggle('dark:text-gray-400', !isActive);
        });
        document.querySelectorAll('.cs-item').forEach(function (item) {
            if (f === 'alles') {
                item.style.display = '';
            } else if (f === 'bel') {
                item.style.display = item.dataset.kind === 'bel' ? '' : 'none';
            } else {
                item.style.display = item.dataset.kind === 'email' ? '' : 'none';
            }
        });
    };

    // ── Submit belstatus ─────────────────────────────────────────
    window.__csSubmit = async function () {
        const note = (document.getElementById('cs-note')?.value || '').trim();
        const sendEmail = document.getElementById('cs-send-email')?.checked || false;
        const btn = document.getElementById('cs-submit');

        if (!selectedStatus) { alert('Selecteer een belstatus'); return; }

        btn.disabled = true;
        const origText = btn.innerHTML;
        btn.innerHTML = '<span class="icon-spinner animate-spin text-base"></span> Opslaan...';

        try {
            const res = await fetch('{{ route('admin.activities.call-statuses.store', $activity->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ status: selectedStatus, omschrijving: note, send_email: sendEmail }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Opslaan mislukt');

            // Reset form
            document.getElementById('cs-note').value = '';
            document.getElementById('cs-send-email').checked = false;

            // Prepend new item to timeline
            __csPrependBelstatus(data.data, statusConfig);

            if (data.send_email) {
                window.dispatchEvent(new CustomEvent('open-email-dialog', {
                    detail: { defaultEmail: data.default_email || null, activityId: data.activity_id }
                }));
            } else {
                // Show flash via emitter if available
                if (window.app?._context?.app?.config?.globalProperties?.$emitter) {
                    window.app._context.app.config.globalProperties.$emitter.emit('add-flash', {
                        type: 'success', message: data.message || 'Belstatus toegevoegd'
                    });
                }
            }
        } catch (err) {
            alert(err.message || 'Er is een fout opgetreden');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    };

    // ── Delete belstatus ─────────────────────────────────────────
    const csBaseUrl = '{{ url('admin/activities/' . $activity->id . '/call-statuses') }}';

    window.__csDeleteBelstatus = async function (id, btn) {
        if (!confirm('Belstatus verwijderen?')) return;
        const item = btn.closest('.cs-item');
        try {
            const res = await fetch(csBaseUrl + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Verwijderen mislukt');
            item?.remove();
            const countEl = document.getElementById('cs-history-count');
            if (countEl) countEl.textContent = String(Math.max(0, (parseInt(countEl.textContent) || 0) - 1));
        } catch (err) {
            alert(err.message || 'Fout bij verwijderen');
        }
    };

    function __csPrependBelstatus(item, cfg) {
        const timeline = document.getElementById('cs-timeline');
        if (!timeline) return;

        const emptyMsg = timeline.querySelector('.text-center');
        if (emptyMsg) emptyMsg.remove();

        const statusCfg = cfg[item.status] || { label: item.status, color: '#555', bg: '#eee' };
        const createdAt = item.created_at
            ? new Date(item.created_at).toLocaleString('nl-NL', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
            : '';

        const div = document.createElement('div');
        div.className = 'cs-item flex gap-3 pb-5';
        div.dataset.kind = 'bel';
        div.innerHTML = `
            <div class="flex flex-col items-center">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm"
                     style="background:${statusCfg.bg};color:${statusCfg.color};">
                    <span class="icon-phone text-base"></span>
                </div>
                <div class="w-px flex-1 bg-gray-200 mt-1.5 mb-[-8px]"></div>
            </div>
            <div class="flex-1 min-w-0 pt-1">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-sm text-gray-900">${statusCfg.label}</span>
                        <span class="text-[10px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5"
                              style="background:${statusCfg.bg};color:${statusCfg.color};">belstatus</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-400">${createdAt}</span>
                        <button type="button"
                            onclick="window.__csDeleteBelstatus(${item.id}, this)"
                            class="p-1 text-gray-300 hover:text-red-500 transition-colors"
                            title="Verwijderen">
                            <span class="icon-delete text-sm"></span>
                        </button>
                    </div>
                </div>
                ${item.omschrijving ? `<div class="mt-1.5 text-sm text-gray-600 leading-relaxed">${item.omschrijving}</div>` : ''}
                <div class="mt-1 text-xs text-gray-400">Toegevoegd door {{ auth()->guard('user')->user()?->name }}</div>
            </div>`;

        timeline.insertBefore(div, timeline.firstChild);

        // Update count badge
        const countEl = document.getElementById('cs-history-count');
        if (countEl) countEl.textContent = String((parseInt(countEl.textContent) || 0) + 1);
    }
})();
</script>
@endpush
