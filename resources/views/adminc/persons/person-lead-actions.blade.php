@php use Illuminate\Database\Eloquent\ModelNotFoundException; @endphp
@props([
    'person',
    'entity',
    'entityId',
    'isLead' => false,
    'isSalesLead' => false,
    'showSyncLink' => true,
    'showAnamnesis' => true,
    'detachRoute' => null,
])

@php
    // Get default email and anamnesis for this person
    $defaultEmail = null;
    if ($person->emails && count($person->emails) > 0) {
        $defaultEmail = collect($person->emails)->firstWhere('is_default', true) ?? $person->emails[0] ?? null;
    }

    $personAnamnesis = null;
    $anamnesisId = null;
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

    if ($personAnamnesis && !empty($personAnamnesis->gvl_form_link)) {
        $anamnesisId = $personAnamnesis->id;
    }

    // Check if person has a patient portal account (Keycloak user)
    $hasPortalAccount = !empty($person->keycloak_user_id);
    $canSendInfoMail = $isLead && $defaultEmail && $hasPortalAccount;

    // Determine return URL for edit action
    $returnUrl = null;
    if ($isLead && $entityId) {
        $returnUrl = route('admin.leads.view', $entityId);
    } elseif ($isSalesLead && $entityId) {
        $returnUrl = route('admin.sales-leads.view', $entityId);
    }
@endphp

<div class="flex items-center gap-1">
    @if ($isLead && $defaultEmail)
        <button
            type="button"
            id="info-mail-{{ $person->id }}-{{ $entityId }}"
            class="icon-mail rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 {{ $canSendInfoMail ? 'text-activity-note-text hover:text-blue-700' : 'text-gray-400 cursor-not-allowed opacity-50' }}"
            title="{{ $hasPortalAccount ? 'Stuur informatieve mail met GVL link' : 'Persoon heeft geen patient portaal account. Maak eerst een portaalaccount aan.' }}"
            @if (!$canSendInfoMail) disabled @endif
            data-person-id="{{ $person->id }}"
            data-lead-id="{{ $entityId }}"
            data-default-email="{{ $defaultEmail['value'] ?? '' }}"
            @if ($anamnesisId) data-anamnesis-id="{{ $anamnesisId }}" data-status-url="{{ route('admin.anamnesis.gvl-form.status', $anamnesisId) }}" @endif
        ></button>
    @endif

    @if (bouncer()->hasPermission('contacts.persons.edit'))
        <a
            href="{{ route('admin.contacts.persons.edit', $person->id) }}{{ $returnUrl ? '?return_url=' . urlencode($returnUrl) : '' }}"
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

    @include('adminc.persons.partials.patientportal-button' , [
        'person' => $person,
        'presentLarge' => false,
        'returnUrl' => $returnUrl,
    ])
    @if ($detachRoute)
        <button
            type="button"
            class="icon-trash rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-status-expired-text hover:text-red-700"
            title="Persoon ontkoppelen"
            onclick="detachPerson({{ $person->id }})"
        ></button>
    @endif
</div>

@if ($isLead)
    @pushOnce('scripts')
    <script type="module">
        (function() {
            const createGvlFormUrl = "{{ route('admin.anamnesis.create-and-attach-gvl-form') }}";

            // Use event delegation on document body to catch all clicks
            // This ensures clicks are caught even if buttons are replaced by Vue
            let globalClickHandlerRegistered = false;
            if (!globalClickHandlerRegistered) {
                document.addEventListener('click', async function(e) {
                    // Check if clicked element or its parent is an info-mail button
                    let target = e.target;
                    let button = null;

                    // Walk up the DOM tree to find the button
                    while (target && target !== document.body) {
                        if (target.id && target.id.startsWith('info-mail-')) {
                            button = target;
                            break;
                        }
                        target = target.parentElement;
                    }

                    if (!button || button.disabled) {
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();

                    if (window.privatescan?.handleInfoMailButtonClick) {
                        await window.privatescan.handleInfoMailButtonClick(button, createGvlFormUrl);
                    }
                }, true); // Use capture phase
                globalClickHandlerRegistered = true;
            }

            // Initialize info mail buttons
            const initInfoMailButtons = () => {
                document.querySelectorAll('[id^="info-mail-"]').forEach(button => {
                    if (button.dataset.initialized === 'true') {
                        return; // Already initialized
                    }

                    button.dataset.initialized = 'true';

                    // Check GVL form status if anamnesis ID is present
                    if (button.dataset.anamnesisId && button.dataset.statusUrl && window.privatescan?.checkGvlFormStatus) {
                        window.privatescan.checkGvlFormStatus(button);
                    }
                });
            };

            // Initialize on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initInfoMailButtons);
            } else {
                initInfoMailButtons();
            }

            // Re-initialize after Vue renders (for dynamic content)
            // Use a debounce to prevent too many re-initializations
            let initTimeout;
            const observer = new MutationObserver(() => {
                clearTimeout(initTimeout);
                initTimeout = setTimeout(() => {
                    initInfoMailButtons();
                }, 100);
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
    </script>
    @endPushOnce
@endif
