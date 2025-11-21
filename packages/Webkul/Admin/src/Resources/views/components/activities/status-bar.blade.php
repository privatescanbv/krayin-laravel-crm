@php
    $status = is_string($activity->status) ? $activity->status : ($activity->status?->value ?? 'active');
    $statusLabels = [
        'in_progress' => 'In behandeling',
        'active' => 'Actief',
        'on_hold' => 'On hold',
        'expired' => 'Verlopen',
        'done' => 'Afgerond',
    ];
    $baseClasses = 'px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer';
    $inactiveClasses = 'bg-white text-gray-700 border-gray-300 hover:bg-neutral-bg dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
    $activeMap = [
        'in_progress' => 'bg-blue-100 text-activity-task-text border-activity-task-border ring-2 ring-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-700 dark:ring-blue-700',
        'active' => 'bg-green-100 text-green-800 text-activity-email-text ring-2 ring-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-700 dark:ring-green-700',
        'on_hold' => 'bg-yellow-100 text-status-on_hold-text border-yellow-400 ring-2 ring-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-700 dark:ring-yellow-700',
        'expired' => 'bg-red-100 text-red-800 border-red-400 ring-2 ring-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700 dark:ring-red-700',
        'done' => 'bg-gray-200 text-gray-800 border-gray-400 ring-2 ring-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:ring-gray-600',
    ];
    $options = ['in_progress','active','on_hold','expired','done'];
@endphp

<div class="flex items-center gap-2" id="activity-status-buttons" data-update-url="{{ route('admin.activities.update', $activity->id) }}" data-csrf="{{ csrf_token() }}">
    @foreach ($options as $opt)
        @php $isDone = $opt === 'done'; @endphp
        <button type="button"
                data-status="{{ $opt }}"
                @if(!$isDone) onclick="window.updateActivityStatus && window.updateActivityStatus('{{ $opt }}')" @endif
                @if($isDone) disabled aria-disabled="true" @endif
                class="status-btn {{ $baseClasses }} {{ $status === $opt ? ($activeMap[$opt] ?? '') : $inactiveClasses }} @if($isDone) cursor-not-allowed opacity-80 @endif">
            {{ $statusLabels[$opt] }}
        </button>
    @endforeach
    @if(!isset($hide_help) || !$hide_help)
        <span class="text-xs text-gray-500">Status past zich aan op basis van datumrange.</span>
    @endif
    <script>
        (function(){
            const container = document.getElementById('activity-status-buttons');
            if (!container) return;
            const url = container.getAttribute('data-update-url');
            const csrf = container.getAttribute('data-csrf');
            const inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-neutral-bg dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
            const map = {
                in_progress: 'bg-blue-100 text-activity-task-text border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                active: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                on_hold: 'bg-yellow-100 text-status-on_hold-text border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                expired: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                done: 'bg-gray-200 text-gray-800 border-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600',
            };
            window.updateActivityStatus = async function(status) {
                try {
                    const params = new URLSearchParams();
                    params.append('_method', 'PUT');
                    params.append('status', status);
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: params.toString()
                    });
                    const body = await (async () => { try { return await res.json(); } catch (_) { return {}; } })();
                    if (!res.ok) {
                        const computed = body && body.status ? body.status : null;
                        const message  = body && body.message ? body.message : 'Status bijwerken mislukt';
                        if (computed) {
                            container.querySelectorAll('button.status-btn').forEach(b => {
                                b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                            });
                            const compBtn = container.querySelector('button.status-btn[data-status="' + computed + '"]');
                            if (compBtn) {
                                compBtn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[computed] || '');
                            }
                        }
                        alert(message);
                        return;
                    }
                    const newStatus = (body && body.status) ? body.status : status;
                    container.querySelectorAll('button.status-btn').forEach(b => {
                        b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                    });
                    const activeBtn = container.querySelector('button.status-btn[data-status="' + newStatus + '"]');
                    if (activeBtn) {
                        activeBtn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[newStatus] || '');
                    }
                } catch (err) {
                    console.error('[activity-status] error', err);
                    alert(err.message || 'Kon status niet bijwerken');
                }
            }
        })();
    </script>
</div>

