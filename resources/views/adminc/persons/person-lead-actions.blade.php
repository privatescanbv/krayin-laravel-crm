@props([
    'person',
    'entity',
    'entityId',
    'isLead' => false,
    'isSalesLead' => false,
    'showSyncLink' => true,
    'detachRoute' => null,
    'overrideReturnUrl' => null,
])

@php
    // Determine return URL for edit action
    if ($overrideReturnUrl) {
        $returnUrl = $overrideReturnUrl;
    } elseif ($isLead && $entityId) {
        $returnUrl = route('admin.leads.view', $entityId);
    } elseif ($isSalesLead && $entityId) {
        $returnUrl = route('admin.sales-leads.view', $entityId);
    } else {
        $returnUrl = null;
    }
@endphp

<div class="flex items-center gap-1">
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

    @include('adminc.persons.partials.patient-impersonate-button' , [
       'person' => $person,
       'presentLarge' => false,
       'returnUrl' => route('admin.contacts.persons.view', $person->id),
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
