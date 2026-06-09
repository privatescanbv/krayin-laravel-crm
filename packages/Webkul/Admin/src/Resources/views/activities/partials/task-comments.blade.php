@php
$totalCount = ($taskComments ?? collect())->count();
@endphp

{{-- ═══════════════════════════════════════════════════════════
     KAART 1: Opmerking toevoegen
     ═══════════════════════════════════════════════════════════ --}}
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Opmerking toevoegen</h2>
        <span class="text-xs text-gray-400 dark:text-gray-500">
            Toegevoegd door <strong class="text-gray-600 dark:text-gray-300">{{ auth()->guard('user')->user()?->name }}</strong>
        </span>
    </div>

    <div class="px-5 py-4">
        <textarea
            id="tc-comment"
            rows="3"
            placeholder="Typ een opmerking…"
            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:border-gray-400 resize-none"
        ></textarea>
        <div class="flex justify-end mt-3">
            <button
                type="button"
                id="tc-submit"
                onclick="window.__tcSubmit()"
                class="inline-flex items-center gap-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 text-sm font-semibold transition-colors"
            >
                <span class="icon-check text-base"></span>
                Opmerking toevoegen
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     KAART 2: History
     ═══════════════════════════════════════════════════════════ --}}
<div class="box-shadow rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Geschiedenis</h2>
        <span id="tc-history-count"
              class="inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold text-xs min-w-[22px] h-[22px] px-1.5">
            {{ $totalCount }}
        </span>
    </div>

    <div class="px-5 py-5" id="tc-timeline">
        @php $currentUserId = auth()->guard('user')->id(); @endphp
        @forelse($taskComments ?? [] as $i => $comment)
            @php $last = $i === $totalCount - 1; @endphp
            <div class="flex gap-3 pb-5" data-comment-id="{{ $comment->id }}">
                <div class="flex flex-col items-center">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                        <span class="icon-note text-base"></span>
                    </div>
                    @if(!$last)<div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-1.5 mb-[-8px]"></div>@endif
                </div>
                <div class="flex-1 min-w-0 pt-1">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            {{ $comment->creator?->name ?? '-' }}
                        </span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $comment->created_at?->format('d-m-Y H:i') }}
                            </span>
                            @if($comment->created_by === $currentUserId)
                                <button type="button"
                                    onclick="window.__tcDeleteComment({{ $comment->id }}, this)"
                                    class="p-1 text-gray-300 hover:text-red-500 transition-colors"
                                    title="Verwijderen">
                                    <span class="icon-delete text-sm"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $comment->comment }}</div>
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">Nog geen opmerkingen.</div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
(function () {
    window.__tcSubmit = async function () {
        const textarea = document.getElementById('tc-comment');
        const comment = (textarea?.value || '').trim();
        const btn = document.getElementById('tc-submit');

        if (!comment) { alert('Typ een opmerking'); return; }

        btn.disabled = true;
        const origText = btn.innerHTML;
        btn.innerHTML = '<span class="icon-spinner animate-spin text-base"></span> Opslaan...';

        try {
            const res = await fetch('{{ route('admin.activities.comments.store', $activity->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ comment }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Opslaan mislukt');

            textarea.value = '';
            __tcPrependComment(data.data);

            if (window.app?._context?.app?.config?.globalProperties?.$emitter) {
                window.app._context.app.config.globalProperties.$emitter.emit('add-flash', {
                    type: 'success', message: 'Opmerking toegevoegd'
                });
            }
        } catch (err) {
            alert(err.message || 'Er is een fout opgetreden');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    };

    // ── Delete comment ───────────────────────────────────────────
    const tcBaseUrl = '{{ url('admin/activities/' . $activity->id . '/comments') }}';

    window.__tcDeleteComment = async function (id, btn) {
        if (!confirm('Opmerking verwijderen?')) return;
        const item = btn.closest('[data-comment-id]');
        try {
            const res = await fetch(tcBaseUrl + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Verwijderen mislukt');
            item?.remove();
            const countEl = document.getElementById('tc-history-count');
            if (countEl) countEl.textContent = String(Math.max(0, (parseInt(countEl.textContent) || 0) - 1));
        } catch (err) {
            alert(err.message || 'Fout bij verwijderen');
        }
    };

    function __tcPrependComment(item) {
        const timeline = document.getElementById('tc-timeline');
        if (!timeline) return;

        const emptyMsg = timeline.querySelector('.text-center');
        if (emptyMsg) emptyMsg.remove();

        const createdAt = item.created_at
            ? new Date(item.created_at).toLocaleString('nl-NL', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
            : '';

        const creatorName = item.creator?.name || '{{ auth()->guard('user')->user()?->name }}';

        const div = document.createElement('div');
        div.className = 'flex gap-3 pb-5';
        div.innerHTML = `
            <div class="flex flex-col items-center">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <span class="icon-note text-base"></span>
                </div>
                <div class="w-px flex-1 bg-gray-200 mt-1.5 mb-[-8px]"></div>
            </div>
            <div class="flex-1 min-w-0 pt-1">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="font-semibold text-sm text-gray-900">${creatorName}</span>
                    <span class="text-xs text-gray-400 shrink-0">${createdAt}</span>
                </div>
                <div class="mt-1.5 text-sm text-gray-600 leading-relaxed whitespace-pre-line">${item.comment}</div>
            </div>`;

        timeline.insertBefore(div, timeline.firstChild);

        const countEl = document.getElementById('tc-history-count');
        if (countEl) countEl.textContent = String((parseInt(countEl.textContent) || 0) + 1);
    }
})();
</script>
@endpush
