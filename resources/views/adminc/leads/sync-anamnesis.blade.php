<x-admin.sync.page-layout
    title="Anamnesis Gegevens Overnemen"
    :header-title="'Oudere Anamnesis overnemen voor ' . $person->name"
    header-description="Vergelijk en neem gegevens over van oudere anamneses."
    :back-route="request()->filled('return_url') ? request('return_url') : $entityUrl"
    :form-action="route('admin.leads.sync-anamnesis-update', $anamnesis->person_id)"
    form-id="sync-anamnesis-form"
    :match-score="$bestMatch ?? null"
    match-score-title="Beste Match Score"
    :redirect-route="request()->filled('return_url') ? request('return_url') : $entityUrl"
>
    <x-slot:headerBefore>
        {!! view_render_event('admin.leads.sync_anamnesis.header.before', ['anamnesis' => $anamnesis]) !!}
    </x-slot>

    <x-slot:headerAfter>
        {!! view_render_event('admin.leads.sync_anamnesis.header.after', ['anamnesis' => $anamnesis]) !!}
    </x-slot>

    @if (request()->filled('return_url'))
        <input type="hidden" name="return_url" value="{{ request('return_url') }}" />
    @endif

    <div class="box-shadow rounded bg-white dark:bg-gray-900">
        <div class="p-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="text-lg font-semibold dark:text-white">Verschillende velden</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                Kies per veld welke waarde gebruikt moet worden.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-fixed">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-48">Veld</th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-48">
                        Huidige Waarde <br>
                        <span class="text-gray-400 font-normal normal-case">
                            <a href="{{ route('admin.anamnesis.edit', $anamnesis->id) }}">{{ $anamnesis->created_at->format('d-m-Y H:i') }}</a>
                        </span><br>
                        @php
                            $currentEntityUrl = $anamnesis->order_id
                                ? route('admin.orders.view', $anamnesis->order_id)
                                : ($anamnesis->sales_id
                                    ? route('admin.sales-leads.view', $anamnesis->sales_id)
                                    : route('admin.leads.view', $anamnesis->lead_id));
                            $currentEntityLabel = match($anamnesis->source_level) {
                                'order' => 'Order',
                                'sales' => 'Sales',
                                default => 'Lead',
                            };
                        @endphp
                        <a href="{{ $currentEntityUrl }}" class="text-blue-500 hover:underline font-normal normal-case">{{ $currentEntityLabel }}</a>
                    </th>

                    @foreach ($olderAnamnises as $oldAnamnesis)
                        @php
                            $oldEntityUrl = $oldAnamnesis->order_id
                                ? route('admin.orders.view', $oldAnamnesis->order_id)
                                : ($oldAnamnesis->sales_id
                                    ? route('admin.sales-leads.view', $oldAnamnesis->sales_id)
                                    : route('admin.leads.view', $oldAnamnesis->lead_id));
                            $oldEntityLabel = match($oldAnamnesis->source_level) {
                                'order' => 'Order',
                                'sales' => 'Sales',
                                default => 'Lead',
                            };
                        @endphp
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-48">
                            <span class="font-normal normal-case text-gray-400">
                                <a href="{{ route('admin.anamnesis.edit', $oldAnamnesis->id) }}">{{ $oldAnamnesis->created_at->format('d-m-Y H:i') }}</a>
                            </span><br>
                            <a href="{{ $oldEntityUrl }}" class="text-blue-500 hover:underline font-normal normal-case">{{ $oldEntityLabel }}</a>
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
                        <td class="px-4 py-4 font-medium text-gray-900 dark:text-white w-48">{{ $labelAndType[0] }}</td>

                        {{-- Nieuwste waarde (Current) --}}
                        <td class="px-4 py-4 w-48">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="choice[{{ $field }}]"
                                    value="current"
                                    checked
                                    class="form-radio shrink-0"
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
                            <td class="px-4 py-4 w-48">
                                @php
                                    $diff = $matchBreakdown[$oldAnamnesis->id]['field_differences'][$field] ?? null;
                                @endphp
                                @if ($diff)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="choice[{{ $field }}]"
                                            value="{{ $oldAnamnesis->id }}"
                                            class="form-radio shrink-0"
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
