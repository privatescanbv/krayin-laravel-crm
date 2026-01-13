@php
    use App\Enums\CallStatus;
@endphp

<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white">Belstatus</h3>
    </div>

    <!-- Add new call status form (moved above the list) -->
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
        <form id="call-status-form" class="mt-2 space-y-2" aria-hidden="false">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Status <span class="text-red-500">*</span>
                </label>
                <select name="status" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Selecteer status...</option>
                    @foreach (CallStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ $status->value === 'spoken' ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Omschrijving
                </label>
                <textarea
                    name="omschrijving"
                    rows="2"
                    class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Optionele notitie over de belstatus..."
                ></textarea>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Taak verplaatsen
                </label>
                <select name="reschedule_days" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Geen verplaatsing</option>
                    @for($i = 1; $i <= 20; $i++)
                        <option value="{{ $i }}">{{ $i }} {{ $i == 1 ? 'dag' : 'dagen' }}</option>
                    @endfor
                </select>
                <p class="text-xs text-gray-500 mt-1">Verplaats de taak met het geselecteerde aantal dagen</p>
            </div>

            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="send_email" value="1" class="rounded border-gray-300 text-activity-note-text shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="ml-2 text-xs font-medium text-gray-700 dark:text-gray-300">E-Mail versturen?</span>
                </label>
                <p class="text-[11px] text-gray-500 mt-1">Na het toevoegen van de belstatus wordt een e-mail dialoog geopend</p>
            </div>

            <button id="call-status-submit" onclick="window.__handleCallStatusSubmit && window.__handleCallStatusSubmit(event)" class="w-full primary-button">
                <i class="icon-plus mr-2"></i>Toevoegen
            </button>
        </form>
    </div>

    <!-- Existing call statuses list -->
    <div class="space-y-3" id="call-status-list">
        @forelse(($callStatuses ?? []) as $item)
            <div class="rounded border p-2 text-sm dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <call-status-icon
                            status="{{ $item->status->value }}"
                            size="w-4 h-4"
                        ></call-status-icon>
                        <span class="font-medium">{{ $item->status->label() }}</span>
                    </div>
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
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define robust global handler so inline onclick always works even if DOM is re-rendered
        window.__handleCallStatusSubmit = async function(e) {
            try {
                if (e) { e.preventDefault && e.preventDefault(); e.stopPropagation && e.stopPropagation(); }

                const form = document.getElementById('call-status-form');
                if (!form) {
                    alert('Form niet gevonden');
                    return false;
                }

                const statusEl = form.querySelector('[name="status"]');
                const omschrEl = form.querySelector('[name="omschrijving"]');
                const rescheduleEl = form.querySelector('[name="reschedule_days"]');
                const sendEmailEl = form.querySelector('[name="send_email"]');
                if (!statusEl || !omschrEl || !rescheduleEl) {
                    alert('Form velden niet gevonden');
                    return false;
                }

                const status = statusEl.value;
                const omschrijving = omschrEl.value;
                let rescheduleDays = rescheduleEl.value;
                const sendEmail = sendEmailEl ? sendEmailEl.checked : false;

                if (status === 'spoken') {
                    rescheduleDays = '';
                    rescheduleEl.value = '';
                } else if (!rescheduleDays) {
                    rescheduleDays = '7';
                    rescheduleEl.value = '7';
                }

                if (!status) {
                    alert('Selecteer een status');
                    return false;
                }

                const res = await fetch('{{ route('admin.activities.call-statuses.store', $activity->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ status, omschrijving, reschedule_days: rescheduleDays, send_email: sendEmail })
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
                    return false;
                }

                const item = data.data;
                const createdAt = item.created_at ? new Date(item.created_at).toLocaleString('nl-NL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                const statusLabels = { 'not_reachable': 'Niet kunnen bereiken', 'voicemail_left': 'Voicemail ingesproken', 'spoken': 'Gesproken' };

                const wrapper = document.createElement('div');
                wrapper.className = 'rounded border p-2 text-sm dark:border-gray-700';
                wrapper.innerHTML =
                    '<div class="flex items-center justify-between">' +
                        '<span class="font-medium">' + (statusLabels[item.status] || item.status) + '</span>' +
                        '<span class="text-xs text-gray-500">' + createdAt + '</span>' +
                    '</div>' +
                    (item.omschrijving ? '<div class="mt-1 text-gray-700 dark:text-gray-300">' + item.omschrijving + '</div>' : '') +
                    (item.creator ? '<div class="mt-1 text-xs text-gray-500">Toegevoegd door: ' + item.creator.name + '</div>' : '');

                list.prepend(wrapper);
                form.reset();

                if (data.send_email) {
                    window.dispatchEvent(new CustomEvent('open-email-dialog', { detail: { defaultEmail: data.default_email || null, activityId: data.activity_id } }));
                }
                return false;
            } catch (e2) {
                console.error('Call status error:', e2);
                alert(e2.message || 'Onbekende fout');
                return false;
            }
        };
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
            // Direct listener (in case the element stays the same)
            statusEl && statusEl.addEventListener('change', applyDefaults);
        }

        // Delegated listener to handle dynamic DOM replacements for status change
        document.addEventListener('change', function(e) {
            const target = e.target;
            if (!target || target.name !== 'status') return;
            const formEl = target.closest('#call-status-form');
            if (!formEl) return;
            const resEl = formEl.querySelector('[name="reschedule_days"]');
            if (!resEl) return;
            if (target.value === 'spoken') {
                resEl.value = '';
            } else if (!resEl.value) {
                resEl.value = '7';
            }
        });
        // Click handling is inline via onclick on the submit button to avoid delegation complexity
    });
</script>
@endpush
