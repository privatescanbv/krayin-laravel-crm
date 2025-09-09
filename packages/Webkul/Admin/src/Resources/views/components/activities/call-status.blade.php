@php
    use App\Enums\CallStatusEnum;
@endphp

<div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white">Belstatus</h3>
    </div>

    <div class="space-y-3" id="call-status-list">
        @forelse(($callStatuses ?? []) as $item)
            <div class="rounded border p-2 text-sm dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{ __($item->status->name) }}</span>
                    <span class="text-xs text-gray-500">{{ $item->created_at?->format('d-m-Y H:i') }}</span>
                </div>
                @if($item->omschrijving)
                    <div class="mt-1 text-gray-700 dark:text-gray-300">{{ $item->omschrijving }}</div>
                @endif
            </div>
        @empty
            <div class="text-sm text-gray-500">Nog geen belstatussen toegevoegd.</div>
        @endforelse
    </div>

    <form id="call-status-form" class="mt-4 space-y-2">
        <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select name="status" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900">
                <option value="{{ CallStatusEnum::NOT_REACHABLE->value }}">Niet kunnen bereiken</option>
                <option value="{{ CallStatusEnum::VOICEMAIL_LEFT->value }}">Voicemail ingesproken</option>
                <option value="{{ CallStatusEnum::SPOKEN->value }}">Gesproken</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Omschrijving</label>
            <textarea name="omschrijving" rows="2" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" placeholder="Korte notitie"></textarea>
        </div>

        <button type="button" id="call-status-submit" class="primary-button w-full">Toevoegen</button>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const url = '{{ route('admin.activities.call-statuses.store', $activity->id) }}';
        const csrfToken = '{{ csrf_token() }}';
        
        const submitBtn = document.getElementById('call-status-submit');
        console.log('[call-status] DOMContentLoaded, submitBtn:', !!submitBtn);
        
        if (submitBtn) {
            console.log('[call-status] Adding click listener to button:', submitBtn);
            
            // Try both click and mousedown events
            submitBtn.addEventListener('click', async function(e) {
                console.log('[call-status] CLICK event fired!', e);
                e.preventDefault();
                e.stopPropagation();
                
                const form = document.getElementById('call-status-form');
                console.log('[call-status] form found:', !!form);
                if (!form) {
                    alert('Form niet gevonden');
                    return;
                }
                
                const statusEl = form.querySelector('[name="status"]');
                const omschrEl = form.querySelector('[name="omschrijving"]');
                console.log('[call-status] elements found:', { statusEl: !!statusEl, omschrEl: !!omschrEl });
                if (!statusEl || !omschrEl) {
                    alert('Form velden niet gevonden');
                    return;
                }

                const status = statusEl.value;
                const omschrijving = omschrEl.value;

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
                        body: JSON.stringify({ status, omschrijving })
                    });

                    if (!res.ok) {
                        let message = 'Toevoegen mislukt';
                        try { const err = await res.json(); message = err.message || message; } catch (_) {}
                        throw new Error(message);
                    }

                    const data = await res.json();
                    const list = document.getElementById('call-status-list');
                    if (!list) {
                        alert('Lijst container niet gevonden');
                        return;
                    }
                    
                    const item = data.data;
                    const createdAt = item.created_at ? new Date(item.created_at).toLocaleString('nl-NL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

                    const wrapper = document.createElement('div');
                    wrapper.className = 'rounded border p-2 text-sm dark:border-gray-700';
                    wrapper.innerHTML = '<div class="flex items-center justify-between"><span class="font-medium">' + item.status + '</span><span class="text-xs text-gray-500">' + createdAt + '</span></div>' + (item.omschrijving ? '<div class="mt-1 text-gray-700 dark:text-gray-300">' + item.omschrijving + '</div>' : '');
                    list.prepend(wrapper);

                    form.reset();
                } catch (e) {
                    console.error(e);
                    alert(e.message);
                }
            });
            
            // Also try mousedown as backup
            submitBtn.addEventListener('mousedown', function(e) {
                console.log('[call-status] MOUSEDOWN event fired!', e);
            });
            
            // Check if button is still the same after a delay
            setTimeout(() => {
                const btnAfterDelay = document.getElementById('call-status-submit');
                console.log('[call-status] Button after delay:', btnAfterDelay === submitBtn, btnAfterDelay);
            }, 1000);
        }
    });
</script>
@endpush
