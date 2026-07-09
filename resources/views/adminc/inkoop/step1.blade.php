<x-admin::layouts>
    <x-slot:title>Inkoop stap 1</x-slot>

    <x-admin::form :action="route('admin.inkoop.save-crm-ids', $invoice->id)" method="POST">
        @method('PUT')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-gray-300">Patienten koppelen</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $percentageResolvedPersons }}% gekoppeld</div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.inkoop.step0', $invoice->id) }}" class="secondary-button">Terug</a>
                    <button type="submit" class="primary-button">Koppelingen opslaan</button>
                    <a href="{{ route('admin.inkoop.step2', $invoice->id) }}" class="secondary-button">Verder</a>
                </div>
            </div>

            @if ($errorMessage)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">{{ $errorMessage }}</div>
            @endif

            <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Geboortedatum</th>
                            <th class="px-4 py-3">CRM match</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-800">
                        @foreach ($patients as $patient)
                            @php
                                $matches      = $patient->crm_matches ?? collect();
                                $singleMatch  = $matches->count() === 1;
                                $isLinked     = ! empty($patient->crm_id);
                                $linkedMatch  = $isLinked ? $matches->firstWhere('id', (int) $patient->crm_id) : null;
                                // A suggestion is auto-selected only when nothing is saved yet and there is exactly one match.
                                $hasSuggestion = ! $isLinked && $singleMatch;
                            @endphp
                            <tr @class(['bg-green-50/40 dark:bg-green-950/20' => $isLinked])>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ trim($patient->firstname . ' ' . $patient->lastname) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ optional($patient->birthday)->format('d-m-Y') }}</td>
                                <td class="px-4 py-3">
                                    <select name="crm_ids[{{ $patient->id }}]" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                        <option value="">Geen koppeling</option>
                                        @foreach ($matches as $match)
                                            <option value="{{ $match->id }}" @selected((int) $patient->crm_id === (int) $match->id || ($hasSuggestion))>
                                                {{ trim(($match->first_name ?? '') . ' ' . ($match->lastname_prefix ? $match->lastname_prefix . ' ' : '') . ($match->last_name ?? '')) }}
                                                {{ $match->date_of_birth ? '(' . $match->date_of_birth->format('d-m-Y') . ')' : '' }}
                                                — #{{ $match->id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isLinked)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                            <span class="icon-tick text-sm"></span> Opgeslagen
                                        </span>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            #{{ $patient->crm_id }}@if ($linkedMatch) — {{ $linkedMatch->name }}@endif
                                        </div>
                                    @elseif ($hasSuggestion)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            Suggestie — nog niet opgeslagen
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            Niet gekoppeld
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($isLinked)
                                        <button type="button" class="secondary-button reset-person-crm" data-url="{{ route('admin.inkoop.reset-person-crm-id', [$invoice->id, $patient->id]) }}">Reset</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-admin::form>

    @pushOnce('scripts')
        <script>
            // The reset buttons live inside the admin form component (Vue-managed),
            // which re-renders its DOM — so directly bound listeners get lost.
            // Delegate from document instead, matching the working pattern elsewhere.
            document.addEventListener('click', async function (e) {
                const button = e.target.closest('.reset-person-crm');
                if (!button) return;

                e.preventDefault();

                if (button.dataset.busy) return;
                button.dataset.busy = '1';

                const originalText = button.textContent;
                button.disabled = true;
                button.textContent = 'Bezig…';

                try {
                    const response = await fetch(button.dataset.url, {
                        method: 'PUT',
                        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'Reset mislukt (status ' + response.status + ')');
                    }

                    // Success: reload so the row shows "Niet gekoppeld".
                    window.location.reload();
                } catch (error) {
                    button.disabled = false;
                    button.textContent = originalText;
                    delete button.dataset.busy;
                    button.classList.add('!border-red-400', '!text-red-600');
                    button.title = error.message;
                    window.alert('Koppeling resetten mislukt: ' + error.message);
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
