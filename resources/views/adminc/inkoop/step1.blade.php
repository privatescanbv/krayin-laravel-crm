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
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-800">
                        @foreach ($patients as $patient)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ trim($patient->firstname . ' ' . $patient->lastname) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ optional($patient->birthday)->format('d-m-Y') }}</td>
                                <td class="px-4 py-3">
                                    <select name="crm_ids[{{ $patient->id }}]" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                        <option value="">Geen koppeling</option>
                                        @php $singleMatch = ($patient->crm_matches ?? collect())->count() === 1; @endphp
                                        @foreach (($patient->crm_matches ?? collect()) as $match)
                                            <option value="{{ $match->id }}" @selected((int) $patient->crm_id === (int) $match->id || (!$patient->crm_id && $singleMatch))>
                                                {{ trim(($match->first_name ?? '') . ' ' . ($match->lastname_prefix ? $match->lastname_prefix . ' ' : '') . ($match->last_name ?? '')) }}
                                                {{ $match->date_of_birth ? '(' . $match->date_of_birth->format('d-m-Y') . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($patient->crm_id)
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
            document.querySelectorAll('.reset-person-crm').forEach((button) => {
                button.addEventListener('click', () => {
                    fetch(button.dataset.url, {
                        method: 'PUT',
                        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                    }).then(() => window.location.reload());
                });
            });
        </script>
    @endPushOnce
</x-admin::layouts>
