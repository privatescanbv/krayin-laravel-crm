@php use Illuminate\Database\Eloquent\ModelNotFoundException; @endphp
@props([
    'entity', // Lead or SalesLead object
    'entityType' => 'lead', // 'lead' or 'salesLead'
    'showAddButton' => false, // Show add person button (only for leads in edit mode)
    'showSyncLink' => true, // Show sync lead to person link (only for leads)
    'showMatchScore' => true, // Show match score component (only for leads)
    'showAnamnesis' => true, // Show anamnesis card (only for leads)
    'detachRoute' => null, // Route for detaching person (optional, only for leads)
])

@php
    $persons = $entity->persons ?? collect();
    $entityId = $entity->id;
    $isLead = $entityType === 'lead';
    $isSalesLead = $entityType === 'salesLead';

    // Determine event name based on entity type
    $eventBefore = $isLead ? 'admin.leads.view.persons.before' : 'admin.sales-leads.view.persons.before';
    $eventAfter = $isLead ? 'admin.leads.view.persons.after' : 'admin.sales-leads.view.persons.after';
@endphp

{!! view_render_event($eventBefore, [$entityType => $entity]) !!}

@if ($persons && $persons->count() > 0)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <b>Personen ({{ $persons->count() }})</b>

                    @if ($showAddButton)
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="icon-plus rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-activity-note-text hover:text-blue-700"
                                title="Persoon toevoegen"
                                onclick="openAddPersonModal()"
                            ></button>
                        </div>
                    @endif
                </div>
            </x-slot>

            <x-slot:content class="!p-0">
                <div class="space-y-3">
                    @foreach ($persons as $person)
                        <div class="border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                            <!-- Person Header -->
                            <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 text-activity-note-text rounded-full flex items-center justify-center text-white font-semibold">
                                        {{ strtoupper(substr($person->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <b>{{ $person->name }} (
                                            @if ($person->age)
                                                {{ $person->age }}
                                            @else
                                                -
                                            @endif
                                        )</b>
                                    </div>
                                </div>

                                @php
                                    // Get default email and anamnesis for this person (needed for details section)
                                    $defaultEmail = null;
                                    if ($person->emails && count($person->emails) > 0) {
                                        $defaultEmail = collect($person->emails)->firstWhere('is_default', true) ?? $person->emails[0] ?? null;
                                    }

                                    $personAnamnesis = null;
                                    if ($showAnamnesis) {
                                        try {
                                            // For sales leads, use the related lead to get anamnesis
                                            if ($isSalesLead && $entity->lead && method_exists($entity->lead, 'findAnamnesisByPersonId')) {
                                                $personAnamnesis = $entity->lead->findAnamnesisByPersonId($person->id);
                                            } elseif ($isLead && method_exists($entity, 'findAnamnesisByPersonId')) {
                                                $personAnamnesis = $entity->findAnamnesisByPersonId($person->id);
                                            }
                                        } catch (ModelNotFoundException $e) {
                                            // Anamnesis not found for this person-lead combination, which is fine
                                            $personAnamnesis = null;
                                        }
                                    }
                                @endphp

                                <x-adminc::persons.person-lead-actions
                                    :person="$person"
                                    :entity="$entity"
                                    :entity-id="$entityId"
                                    :is-lead="$isLead"
                                    :is-sales-lead="$isSalesLead"
                                    :show-sync-link="$showSyncLink"
                                    :show-anamnesis="$showAnamnesis"
                                    :detach-route="$detachRoute"
                                />
                            </div>

                            <!-- Always Expanded Content -->
                            <div class="person-details" id="person-details-{{ $person->id }}">
                                <div class="px-4 pb-3 border-t border-gray-200 dark:border-gray-700">

                                    <!-- Person Details -->
                                    <div class="text-sm space-y-2">
                                        @if ($showMatchScore && $isLead)
                                            <v-match-score person-id="{{ $person->id }}"
                                                           lead-id="{{ $entityId }}"></v-match-score>
                                        @endif

                                        @if ($defaultEmail)
                                            <div>
                                                <a href="mailto:{{ $defaultEmail['value'] }}"
                                                   class="text-activity-note-text hover:text-activity-task-text">
                                                    {{ $defaultEmail['value'] }}
                                                </a>
                                            </div>
                                        @endif

                                        @if ($showAnamnesis && $personAnamnesis)
                                            <!-- Anamnesis for this specific person -->
                                            <x-adminc::anamnesis.card :anamnesis="$personAnamnesis"/>
                                        @endif
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

{!! view_render_event($eventAfter, [$entityType => $entity]) !!}

@if ($showMatchScore && $isLead)
    @include('admin::components.match-score')
@endif

