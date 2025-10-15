{!! view_render_event('admin.leads.view.persons.before', ['lead' => $lead]) !!}

@if ($lead->persons && $lead->persons->count() > 0)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <h4>Personen ({{ $lead->persons->count() }})</h4>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="icon-plus rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 text-blue-600 hover:text-blue-700"
                            title="Persoon toevoegen"
                            onclick="openAddPersonModal()"
                        ></button>
                    </div>
                </div>
            </x-slot>

            <x-slot:content class="!p-0">
                <div class="space-y-3">
                    @foreach ($lead->persons as $person)
                        <div class="border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                            <!-- Person Header -->
                            <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                        {{ strtoupper(substr($person->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <h5 class="font-semibold dark:text-white">{{ $person->name }}  (
                                            @if ($person->age)
                                                {{ $person->age }}
                                            @else
                                                -
                                            @endif
                                        )</h5>
                                        @if ($person->organization)
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $person->organization->name }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                                        <a
                                            href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                                            class="icon-edit rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                            title="Wijzig persoon"
                                        ></a>
                                    @endif
                                    <a
                                        href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                        class="icon-eye rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                        title="Bekijk persoon"
                                    ></a>
                                    <!-- Sync with lead link -->
                                    <a
                                        href="{{ route('admin.contacts.persons.edit_with_lead', ['personId' => $person->id, 'leadId' => $lead->id]) }}"
                                        class="rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 text-green-600 hover:text-green-700"
                                        title="Synchroniseer gegevens met lead"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </a>
                                    <button
                                        type="button"
                                        class="icon-trash rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 text-red-600 hover:text-red-700"
                                        title="Persoon ontkoppelen"
                                        onclick="detachPerson({{ $person->id }})"
                                    ></button>
                                </div>
                            </div>

                            <!-- Always Expanded Content -->
                            <div class="person-details" id="person-details-{{ $person->id }}">
                                <div class="px-4 pb-3 border-t border-gray-200 dark:border-gray-700">

                            <!-- Person Details -->
                            <div class="text-sm space-y-2">
                                <v-match-score person-id="{{ $person->id }}" lead-id="{{ $lead->id }}"></v-match-score>
                                @php
                                    $defaultEmail = null;
                                    if ($person->emails && count($person->emails) > 0) {
                                        $defaultEmail = collect($person->emails)->firstWhere('is_default', true) ?? $person->emails[0] ?? null;
                                    }
                                    $personAnamnesis = $lead->findAnamnesisByPersonId($person->id);
                                @endphp

                                @if ($defaultEmail)
                                    <div>
                                        <a href="mailto:{{ $defaultEmail['value'] }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $defaultEmail['value'] }}
                                        </a>
                                    </div>
                                @endif

                                <!-- Anamnesis for this specific person -->
                                <x-admin::anamnesis.card :anamnesis="$personAnamnesis" />
                                </div>
                            </div>
                        </div>
                        </div>
                    @endforeach
                </div>
            </x-slot:content>
        </x-admin::accordion>
    </div>
@else
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <i class="icon-users text-4xl mb-2"></i>
            <p class="font-medium">Geen contactpersonen gekoppeld</p>
        </div>
    </div>
@endif

{!! view_render_event('admin.leads.view.persons.after', ['lead' => $lead]) !!}

@include('admin::components.match-score')
