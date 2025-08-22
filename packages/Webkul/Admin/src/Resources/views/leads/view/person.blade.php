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
                            <!-- Collapsible Header -->
                            <div class="flex items-center justify-between p-4 cursor-pointer" onclick="togglePersonCard({{ $person->id }})">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                        {{ strtoupper(substr($person->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <h5 class="font-semibold dark:text-white">{{ $person->name }}</h5>
                                        @if ($person->organization)
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $person->organization->name }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <!-- Expand/Collapse Icon -->
                                    <button
                                        type="button"
                                        class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 hover:bg-blue-200 dark:bg-blue-800 dark:hover:bg-blue-700 transition-all person-toggle-icon border border-blue-300 dark:border-blue-600"
                                        id="toggle-icon-{{ $person->id }}"
                                        title="Klik om details te tonen/verbergen"
                                        onclick="event.stopPropagation(); togglePersonCard({{ $person->id }})"
                                    >
                                        <!-- Chevron Down Icon -->
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-300 transition-transform duration-200" id="toggle-svg-{{ $person->id }}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                        <!-- Fallback text if icon doesn't work -->
                                        <span class="sr-only">Toggle</span>
                                    </button>

                                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                                        <a
                                            href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                                            class="icon-edit rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                            title="Wijzig persoon"
                                            onclick="event.stopPropagation()"
                                        ></a>
                                    @endif
                                    <a
                                        href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                        class="icon-eye rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                        title="Bekijk persoon"
                                        onclick="event.stopPropagation()"
                                    ></a>
                                    <button
                                        type="button"
                                        class="icon-trash rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 text-red-600 hover:text-red-700"
                                        title="Persoon ontkoppelen"
                                        onclick="event.stopPropagation(); detachPerson({{ $person->id }})"
                                    ></button>
                                </div>
                            </div>

                            <!-- Collapsible Content -->
                            <div class="person-details" id="person-details-{{ $person->id }}" style="display: none;">
                                <div class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700">

                            <!-- Person Details -->
                            <div class="text-sm space-y-2">
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
                                @if ($personAnamnesis)
                                    <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <h6 class="text-xs font-semibold text-blue-800 dark:text-blue-200">Anamnese</h6>
                                            <a
                                                href="{{ route('admin.anamnesis.edit', $personAnamnesis->id) }}"
                                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                                title="Anamnese bewerken"
                                            >
                                                <i class="icon-edit"></i>
                                            </a>
                                        </div>

                                        <div class="space-y-1 text-xs">
                                            @if ($personAnamnesis->height || $personAnamnesis->weight)
                                                <div class="flex gap-3">
                                                    @if ($personAnamnesis->height)
                                                        <span class="text-gray-600 dark:text-gray-400">{{ $personAnamnesis->height }}cm</span>
                                                    @endif
                                                    @if ($personAnamnesis->weight)
                                                        <span class="text-gray-600 dark:text-gray-400">{{ $personAnamnesis->weight }}kg</span>
                                                    @endif
                                                </div>
                                            @endif

                                            @php
                                                $conditions = collect([
                                                    'metals' => 'Metaal',
                                                    'medications' => 'Medicatie',
                                                    'glaucoma' => 'Glaucoom',
                                                    'claustrophobia' => 'Claustrofobisch',
                                                    'heart_surgery' => 'Hartoperatie',
                                                    'diabetes' => 'Diabetes',
                                                    'smoking' => 'Rookt',
                                                ])->filter(function($label, $field) use ($personAnamnesis) {
                                                    return $personAnamnesis->{$field} == 1;
                                                });
                                            @endphp

                                            @if ($conditions->isNotEmpty())
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach ($conditions as $field => $label)
                                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                            {{ $label }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
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
            <p class="text-sm">Klik op "Persoon toevoegen" om contactpersonen te koppelen</p>
            <button
                type="button"
                class="mt-3 secondary-button"
                onclick="openAddPersonModal()"
            >
                <i class="icon-plus text-xs"></i>
                Persoon toevoegen
            </button>
        </div>
    </div>
@endif

{!! view_render_event('admin.leads.view.persons.after', ['lead' => $lead]) !!}

@pushOnce('scripts')
<script>
function openAddPersonModal() {
    // TODO: Implement modal for adding person to lead
    alert('Add person modal - to be implemented');
}

function detachPerson(personId) {
    if (confirm('Weet je zeker dat je deze persoon wilt ontkoppelen van de lead?')) {
        const leadId = {{ $lead->id }};

        fetch(`/admin/leads/${leadId}/detach-person/${personId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the person card from the DOM
                const personCard = document.querySelector(`#person-details-${personId}`).closest('.border');
                personCard.remove();

                // Show success message
                window.location.reload(); // Reload to update person count and anamnesis
            } else {
                alert('Fout: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Er is een fout opgetreden bij het ontkoppelen van de persoon.');
        });
    }
}

function togglePersonCard(personId) {
    const details = document.getElementById(`person-details-${personId}`);
    const icon = document.getElementById(`toggle-icon-${personId}`);
    const svg = document.getElementById(`toggle-svg-${personId}`);

    if (details.style.display === 'none') {
        // Expand
        details.style.display = 'block';
        svg.style.transform = 'rotate(180deg)';
        icon.title = 'Klik om details te verbergen';
        icon.classList.remove('bg-blue-100', 'hover:bg-blue-200');
        icon.classList.add('bg-blue-200', 'hover:bg-blue-300');
    } else {
        // Collapse
        details.style.display = 'none';
        svg.style.transform = 'rotate(0deg)';
        icon.title = 'Klik om details te tonen';
        icon.classList.remove('bg-blue-200', 'hover:bg-blue-300');
        icon.classList.add('bg-blue-100', 'hover:bg-blue-200');
    }
}

// Initialize all person cards as collapsed on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.person-details').forEach(function(details) {
        details.style.display = 'none';
    });
});
</script>
@endPushOnce
