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
                    <h4>Personen ({{ $persons->count() }})</h4>

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
                                        <h5 class="font-semibold dark:text-white">{{ $person->name }} (
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
                                    @php
                                        // Get default email and anamnesis for this person
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

                                        $hasGvlLink = false;
                                        if ($personAnamnesis && !empty($personAnamnesis->gvl_form_link)) {
                                            $hasGvlLink = true;
                                        }
                                    @endphp
                                    @if ($isLead && $defaultEmail)
                                        <button
                                            type="button"
                                            id="info-mail-{{ $person->id }}-{{ $entityId }}"
                                            class="icon-mail rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 {{ $hasGvlLink ? 'text-activity-note-text hover:text-blue-700' : 'text-gray-400 cursor-not-allowed opacity-50' }}"
                                            title="{{ $hasGvlLink ? 'Stuur informatieve mail met GVL link' : 'GVL formulier link ontbreekt. Koppel eerst een GVL formulier aan de anamnesis.' }}"
                                            @if (!$hasGvlLink) disabled @endif
                                            data-person-id="{{ $person->id }}"
                                            data-lead-id="{{ $entityId }}"
                                            data-default-email="{{ $defaultEmail['value'] ?? '' }}"
                                        ></button>
                                    @endif
                                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                                        <a
                                            href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                                            class="icon-edit rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950"
                                            title="Wijzig persoon"
                                        ></a>
                                    @endif
                                    <a
                                        href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                        class="icon-eye rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950"
                                        title="Bekijk persoon"
                                    ></a>
                                    @if ($showSyncLink && $isLead)
                                        <!-- Sync lead to person link (replaces edit-with-lead) -->
                                        <a
                                            href="{{ route('admin.leads.sync-lead-to-person', ['leadId' => $entityId, 'personId' => $person->id]) }}"
                                            class="rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-status-active-text hover:text-green-700"
                                            title="Gegevens overnemen (lead → person)"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </a>
                                    @endif
                                    @if ($detachRoute)
                                        <button
                                            type="button"
                                            class="icon-trash rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-status-expired-text hover:text-red-700"
                                            title="Persoon ontkoppelen"
                                            onclick="detachPerson({{ $person->id }})"
                                        ></button>
                                    @endif
                                </div>
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

@if ($isLead)
    @pushOnce('scripts')
    <script type="module">
        (function() {
            // Initialize info mail buttons
            const initInfoMailButtons = () => {
                document.querySelectorAll('[id^="info-mail-"]').forEach(button => {
                    if (button.dataset.initialized === 'true') {
                        return; // Already initialized
                    }

                    button.dataset.initialized = 'true';

                    button.addEventListener('click', async function(e) {
                        e.preventDefault();

                        if (this.disabled) {
                            return;
                        }

                        const personId = this.dataset.personId;
                        const leadId = this.dataset.leadId;
                        const defaultEmail = this.dataset.defaultEmail;

                        if (!personId || !leadId || !defaultEmail) {
                            return;
                        }

                        // Use the window event system to open mail dialog
                        const payload = {
                            defaultEmail: defaultEmail,
                            subject: 'Informatie over uw aanvraag',
                            body: '',
                            emails: [{ value: defaultEmail, is_default: true }],
                            lead_id: leadId,
                            person_id: personId,
                            default_template: 'informatief-met-gvl',
                            entity_type: 'gvl',
                        };

                        // Dispatch event to open mail dialog
                        window.dispatchEvent(new CustomEvent('open-email-dialog', {
                            detail: payload
                        }));

                        // Wait for modal to open, then set template
                        // Use a retry mechanism to ensure form is ready
                        const setupFormAndTemplate = (retries = 5) => {
                            const form = document.querySelector('form[name="mail-action-form"]');
                            if (!form && retries > 0) {
                                setTimeout(() => setupFormAndTemplate(retries - 1), 200);
                                return;
                            }

                            if (form) {
                                // Add lead_id and person_id to form for template resolution
                                let leadIdInput = form.querySelector('[name="lead_id"]');
                                if (!leadIdInput) {
                                    leadIdInput = document.createElement('input');
                                    leadIdInput.type = 'hidden';
                                    leadIdInput.name = 'lead_id';
                                    form.appendChild(leadIdInput);
                                }
                                leadIdInput.value = leadId;

                                let personIdInput = form.querySelector('[name="person_id"]');
                                if (!personIdInput) {
                                    personIdInput = document.createElement('input');
                                    personIdInput.type = 'hidden';
                                    personIdInput.name = 'person_id';
                                    form.appendChild(personIdInput);
                                }
                                personIdInput.value = personId;

                                // Store IDs in data attributes for loadTemplate to use
                                form.dataset.leadId = leadId;
                                form.dataset.personId = personId;

                                // Set template (this will trigger loadTemplate)
                                setTimeout(() => {
                                    const templateSelect = document.querySelector('[name="email_template"]');
                                    if (templateSelect) {
                                        templateSelect.value = 'informatief';
                                        templateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }, 100);
                            }
                        };

                        setTimeout(() => setupFormAndTemplate(), 300);
                    });
                });
            };

            // Initialize on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initInfoMailButtons);
            } else {
                initInfoMailButtons();
            }

            // Re-initialize after Vue renders (for dynamic content)
            const observer = new MutationObserver(() => {
                initInfoMailButtons();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
    </script>
    @endPushOnce
@endif
