<x-admin.sync.page-layout
    title="Anamnesis Gegevens Overnemen"
    :header-title="'Oudere Anamnesis overnemen voor ' . $person->name"
    header-description="Vergelijk en neem gegevens over van oudere anamneses."
    :back-route="route('admin.leads.view', $lastLeadId)"
    :form-action="route('admin.leads.sync-anamnesis-update', $anamnesis->person_id)"
    form-id="sync-anamnesis-form"
    :match-score="$bestMatch ?? null"
    match-score-title="Beste Match Score"
    :redirect-route="route('admin.leads.view', $lastLeadId)"
>
    <x-slot:headerBefore>
        {!! view_render_event('admin.leads.sync_anamnesis.header.before', ['anamnesis' => $anamnesis]) !!}
    </x-slot>

    <x-slot:headerAfter>
        {!! view_render_event('admin.leads.sync_anamnesis.header.after', ['anamnesis' => $anamnesis]) !!}
    </x-slot>

    <div class="box-shadow rounded bg-white dark:bg-gray-900">
        <div class="p-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="text-lg font-semibold dark:text-white">Verschillende velden</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                Kies per veld welke waarde gebruikt moet worden.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Veld</th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Huidige Waarde <br>
                        <span class="text-gray-400 font-normal">
                            <a href="{{ route('admin.anamnesis.edit', $anamnesis->id) }}">{{ $anamnesis->created_at->format('d-m-Y H:i') }}</a></span>
                    </th>

                    @foreach ($olderAnamnises as $oldAnamnesis)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ route('admin.anamnesis.edit', $oldAnamnesis->id) }}">
                            {{ $oldAnamnesis->created_at->format('d-m-Y H:i') }}
                            </a>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                @php
                    $allDiffFields = [];
                    foreach($matchBreakdown as $res) {
                        if(isset($res['field_differences'])) {
                            foreach($res['field_differences'] as $f => $d) {
                                $allDiffFields[$f] = [$d['label'], $d['type']];
                            }
                        }
                    }
                @endphp

                @foreach ($allDiffFields as $field => $labelAndType)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-4 font-medium text-gray-900 dark:text-white">{{ $labelAndType[0] }}</td>

                        {{-- Nieuwste waarde (Current) --}}
                        <td class="px-4 py-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="choice[{{ $field }}]"
                                    value="current"
                                    checked
                                    class="text-activity-note-text border-gray-300 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-900 dark:text-white">
                                   @if ($labelAndType[1]=== 'boolean')
                                        {{ filter_var($anamnesis->$field , FILTER_VALIDATE_BOOLEAN) ? 'Waar' : 'Onwaar' }}
                                    @else
                                        {{ $anamnesis->$field }}
                                    @endif
                                </span>
                            </label>
                        </td>

                        {{-- Voor elke oudere anamnese --}}
                        @foreach ($olderAnamnises as $oldAnamnesis)
                            <td class="px-4 py-4">
                                @php
                                    $diff = $matchBreakdown[$oldAnamnesis->id]['field_differences'][$field] ?? null;
                                @endphp
                                @if ($diff)
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="choice[{{ $field }}]"
                                            value="{{ $oldAnamnesis->id }}"
                                            class="text-activity-note-text border-gray-300 focus:ring-blue-500"
                                        >
                                        <span class="text-sm text-gray-600 dark:text-gray-300">
                                            @if ($diff['type'] === 'boolean')
                                                {{ filter_var($diff['old_value'] , FILTER_VALIDATE_BOOLEAN) ? 'Waar' : 'Onwaar' }}
                                            @else
                                                {{ $diff['old_value'] }}
                                            @endif
                                        </span>
                                    </label>
                                @else
                                    <em class="text-gray-400 text-xs">Geen verschil</em>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <div class="flex justify-end items-center">
                <button type="submit" class="primary-button">
                    Gegevens overnemen
                </button>
            </div>
        </div>
    </div>

    <x-slot:contentAfter>
        {!! view_render_event('admin.leads.sync_anamnesis.content.after', ['$anamnesis' => $anamnesis]) !!}
    </x-slot>
</x-admin.sync.page-layout>
