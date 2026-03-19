<x-admin.sync.page-layout
    title="Lead gegevens overnemen naar Person"
    header-title="Lead gegevens overnemen naar Person"
    :header-description="'Overneem gegevens van Lead <strong>' . $lead->name . '</strong> naar Person <strong>' . $person->name . '</strong>'"
    :back-route="route('admin.leads.view', $lead->id)"
    :form-action="route('admin.leads.sync-lead-to-person-update', [$lead->id, $person->id])"
    form-id="sync-lead-to-person-form"
    :match-score="$matchBreakdown"
    match-score-title="Match Score"
    :redirect-route="route('admin.leads.view', $lead->id)"
>
    <x-slot:headerBefore>
        {!! view_render_event('admin.leads.sync_lead_to_person.header.before', ['lead' => $lead, 'person' => $person]) !!}
    </x-slot>

    <x-slot:breadcrumbs>
        <div class="flex cursor-pointer items-center">
            <x-admin::breadcrumbs name="leads.sync_lead_to_person" :entity="$lead" />
        </div>
    </x-slot>

    <x-slot:headerAfter>
        {!! view_render_event('admin.leads.sync_lead_to_person.header.after', ['lead' => $lead, 'person' => $person]) !!}
    </x-slot>

    @if (empty($matchBreakdown['field_differences']))
        <div class="flex flex-col items-center justify-center py-16">
            <div class="text-6xl text-status-active-text mb-4">
                <i class="icon-check-circle"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2 dark:text-white">Alle gegevens komen overeen</h3>
            <p class="text-gray-600 dark:text-gray-300 text-center max-w-md">
                Alle ingevulde velden van de lead komen overeen met de persoon gegevens.
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
                        @foreach ($matchBreakdown['field_differences'] as $field => $difference)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-4 w-36">
                                    <div class="flex flex-col gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="choice[{{ $field }}]"
                                                value="lead"
                                                class="form-radio"
                                                checked
                                            >
                                            <span class="text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">Gebruik lead</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="choice[{{ $field }}]"
                                                value="person"
                                                class="form-radio"
                                            >
                                            <span class="text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">Gebruik person</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <label for="sync_{{ $field }}" class="font-medium text-gray-900 dark:text-white cursor-pointer">
                                        {{ $difference['label'] }}
                                    </label>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-activity-note-text dark:text-blue-400 font-medium">
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

    <x-slot:contentAfter>
        {!! view_render_event('admin.leads.sync_lead_to_person.content.after', ['lead' => $lead, 'person' => $person]) !!}
    </x-slot>
</x-admin.sync.page-layout>
