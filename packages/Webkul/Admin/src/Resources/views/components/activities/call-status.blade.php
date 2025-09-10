@php
    use App\Enums\CallStatus;
@endphp

<div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white">Belstatus</h3>
    </div>

    <div class="space-y-3" id="call-status-list">
        @forelse(($callStatuses ?? []) as $item)
            <div class="rounded border p-2 text-sm dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{ $item->status->label() }}</span>
                    <span class="text-xs text-gray-500">{{ $item->created_at?->format('d-m-Y H:i') }}</span>
                </div>
                @if($item->omschrijving)
                    <div class="mt-1 text-gray-700 dark:text-gray-300">{{ $item->omschrijving }}</div>
                @endif
                @if($item->creator)
                    <div class="mt-1 text-xs text-gray-500">Toegevoegd door: {{ $item->creator->name }}</div>
                @endif
            </div>
        @empty
            <div class="text-sm text-gray-500">Nog geen belstatussen toegevoegd.</div>
        @endforelse
    </div>

    <!-- Add new call status form -->
    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Nieuwe belstatus toevoegen</h4>
        
        <form id="call-status-form" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Status <span class="text-red-500">*</span>
                </label>
                <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Selecteer status...</option>
                    @foreach (CallStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ $status->value === 'spoken' ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Omschrijving
                </label>
                <textarea 
                    name="omschrijving" 
                    rows="3" 
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" 
                    placeholder="Optionele notitie over de belstatus..."
                ></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Taak verplaatsen
                </label>
                <select name="reschedule_days" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Geen verplaatsing</option>
                    @for($i = 1; $i <= 20; $i++)
                        <option value="{{ $i }}">{{ $i }} {{ $i == 1 ? 'dag' : 'dagen' }}</option>
                    @endfor
                </select>
                <p class="text-xs text-gray-500 mt-1">Verplaats de taak met het geselecteerde aantal dagen</p>
            </div>

            <button type="button" id="call-status-submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i class="icon-plus mr-2"></i>Belstatus toevoegen
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const url = '{{ route('admin.activities.call-statuses.store', $activity->id) }}';
        const csrfToken = '{{ csrf_token() }}';
        
        // Initialize defaults on load and when status changes
        const form = document.getElementById('call-status-form');
        if (form) {
            const statusEl = form.querySelector('[name="status"]');
            const rescheduleEl = form.querySelector('[name="reschedule_days"]');
            const applyDefaults = () => {
                if (!statusEl || !rescheduleEl) return;
                if (statusEl.value === 'spoken') {
                    rescheduleEl.value = '';
                } else if (!rescheduleEl.value) {
                    rescheduleEl.value = '7';
                }
            };
            applyDefaults();
            statusEl?.addEventListener('change', applyDefaults);
        }

        // Use event delegation to handle button clicks
        document.addEventListener('click', async function(e) {
            if (e.target && e.target.id === 'call-status-submit') {
                e.preventDefault();
                e.stopPropagation();
                
                const form = document.getElementById('call-status-form');
                if (!form) {
                    alert('Form niet gevonden');
                    return;
                }
                
                const statusEl = form.querySelector('[name="status"]');
                const omschrEl = form.querySelector('[name="omschrijving"]');
                const rescheduleEl = form.querySelector('[name="reschedule_days"]');
                if (!statusEl || !omschrEl || !rescheduleEl) {
                    alert('Form velden niet gevonden');
                    return;
                }

                const status = statusEl.value;
                const omschrijving = omschrEl.value;
                let rescheduleDays = rescheduleEl.value;

                // Defaults: spoken => no move, others => 7 days
                if (status === 'spoken') {
                    rescheduleDays = '';
                    rescheduleEl.value = '';
                } else if (!rescheduleDays) {
                    rescheduleDays = '7';
                    rescheduleEl.value = '7';
                }

                if (!status) {
                    alert('Selecteer een status');
                    return;
                }

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ status, omschrijving, reschedule_days: rescheduleDays })
                    });

                    if (!res.ok) {
                        let message = 'Toevoegen mislukt';
                        try { 
                            const err = await res.json(); 
                            message = err.message || message; 
                        } catch (_) {}
                        throw new Error(message);
                    }

                    const data = await res.json();
                    const list = document.getElementById('call-status-list');
                    if (!list) {
                        alert('Lijst container niet gevonden');
                        return;
                    }
                    
                    const item = data.data;
                    const createdAt = item.created_at ? 
                        new Date(item.created_at).toLocaleString('nl-NL', { 
                            day: '2-digit', 
                            month: '2-digit', 
                            year: 'numeric', 
                            hour: '2-digit', 
                            minute: '2-digit' 
                        }) : '';

                    const statusLabels = {
                        'not_reachable': 'Niet kunnen bereiken',
                        'voicemail_left': 'Voicemail ingesproken',
                        'spoken': 'Gesproken'
                    };
                    
                    const wrapper = document.createElement('div');
                    wrapper.className = 'rounded border p-2 text-sm dark:border-gray-700';
                    wrapper.innerHTML = 
                        '<div class="flex items-center justify-between">' +
                            '<span class="font-medium">' + (statusLabels[item.status] || item.status) + '</span>' +
                            '<span class="text-xs text-gray-500">' + createdAt + '</span>' +
                        '</div>' +
                        (item.omschrijving ? 
                            '<div class="mt-1 text-gray-700 dark:text-gray-300">' + item.omschrijving + '</div>' : 
                            ''
                        ) +
                        (item.creator ? 
                            '<div class="mt-1 text-xs text-gray-500">Toegevoegd door: ' + item.creator.name + '</div>' : 
                            ''
                        );
                    
                    list.prepend(wrapper);
                    form.reset();
                } catch (e) {
                    console.error('Call status error:', e);
                    alert(e.message);
                }
            }
        });
    });
</script>
@endpush
