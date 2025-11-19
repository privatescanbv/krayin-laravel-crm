<x-admin::layouts>
    <x-slot:title>
        Lead gegevens overnemen naar Person
    </x-slot>

    {!! view_render_event('admin.leads.sync_lead_to_person.header.before', ['lead' => $lead, 'person' => $person]) !!}

    <!-- Page Header -->
    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="flex flex-col gap-2">
            <div class="flex cursor-pointer items-center">
                <x-admin::breadcrumbs name="leads.sync_lead_to_person" :entity="$lead" />
            </div>

            <div class="text-xl font-bold dark:text-white">
                Lead gegevens overnemen naar Person
            </div>

            <p class="text-gray-600 dark:text-gray-300">
                Overneem gegevens van Lead <strong>{{ $lead->name }}</strong> naar Person <strong>{{ $person->name }}</strong>
            </p>
        </div>

        <div class="flex items-center gap-x-2.5">
            <a
                href="{{ route('admin.leads.view', $lead->id) }}"
                class="secondary-button"
            >
                @lang('admin::app.account.edit.back-btn')
            </a>
        </div>
    </div>

    {!! view_render_event('admin.leads.sync_lead_to_person.header.after', ['lead' => $lead, 'person' => $person]) !!}

    <form id="sync-lead-to-person-form" action="{{ route('admin.leads.sync-lead-to-person-update', [$lead->id, $person->id]) }}" method="POST">
        @csrf

        <div class="mt-3.5">
            <!-- Match Score Display -->
            <div class="box-shadow rounded bg-white dark:bg-gray-900 mb-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-lg font-semibold dark:text-white">Match Score</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                        {{ $matchBreakdown['matching_fields'] }} van {{ $matchBreakdown['total_fields'] }} velden komen overeen
                    </p>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-4">
                        <div class="w-32 h-4 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300 {{ $matchBreakdown['percentage'] >= 80 ? 'bg-succes' : ($matchBreakdown['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ $matchBreakdown['percentage'] }}%"></div>
                        </div>
                        <span class="text-lg font-medium {{ $matchBreakdown['percentage'] >= 80 ? 'text-succes' : ($matchBreakdown['percentage'] >= 50 ? 'text-yellow-600' : 'text-error') }}">
                            {{ $matchBreakdown['percentage'] }}%
                        </span>
                    </div>
                </div>
            </div>

            @if(empty($matchBreakdown['field_differences']))
                <div class="flex flex-col items-center justify-center py-16">
                    <div class="text-6xl text-green-500 mb-4">
                        <i class="icon-check-circle"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 dark:text-white">Alle gegevens komen overeen</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-center max-w-md">
                        Alle ingevulde velden van de lead komen overeen met de person gegevens.
                    </p>
                </div>
            @else
                <div class="box-shadow rounded bg-white dark:bg-gray-900">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-lg font-semibold dark:text-white">Verschillende velden</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                            Kies per veld welke waarde gebruikt moet worden. Standaard wordt de lead-waarde gebruikt.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="w-56 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Keuze
                                    </th>
                                    <th class="w-48 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Veld
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Lead Waarde
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Person Waarde
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach($matchBreakdown['field_differences'] as $field => $difference)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-4">
                                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="choice[{{ $field }}]"
                                                        value="lead"
                                                        class="text-blue-600 border-gray-300 focus:ring-blue-500"
                                                        checked
                                                    >
                                                    <span class="text-xs text-gray-600 dark:text-gray-300">Gebruik lead</span>
                                                </label>
                                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="choice[{{ $field }}]"
                                                        value="person"
                                                        class="text-blue-600 border-gray-300 focus:ring-blue-500"
                                                    >
                                                    <span class="text-xs text-gray-600 dark:text-gray-300">Gebruik person</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <label for="sync_{{ $field }}" class="font-medium text-gray-900 dark:text-white cursor-pointer">
                                                {{ $difference['label'] }}
                                            </label>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">
                                                {{ $difference['lead_value'] }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                                {{ $difference['person_value'] }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 box-shadow rounded bg-white dark:bg-gray-900 p-4">
                    <div class="flex justify-end items-center">
                        <button type="submit" class="primary-button">
                            Gegevens overnemen
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </form>

    {!! view_render_event('admin.leads.sync_lead_to_person.content.after', ['lead' => $lead, 'person' => $person]) !!}
</x-admin::layouts>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission
            const form = document.getElementById('sync-lead-to-person-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Bezig met overnemen...';

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.message) {
                            // Show success message briefly
                            alert(data.message);
                        }

                        // Redirect immediately if URL provided
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            // Fallback: redirect to lead view
                            window.location.href = "{{ route('admin.leads.view', $lead->id) }}";
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Er is een fout opgetreden bij het overnemen: ' + error.message);

                        // Restore button state on error
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
                });
            }
        });
    </script>
@endpush
