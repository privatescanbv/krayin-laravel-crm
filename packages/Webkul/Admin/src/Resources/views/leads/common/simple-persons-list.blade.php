{!! view_render_event('admin.leads.simple_persons.before') !!}

<div class="flex flex-col gap-3">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold dark:text-white">
            Contactpersonen ({{ $persons->count() }})
        </h3>
        <button
            type="button"
            class="secondary-button"
            onclick="alert('Persoon toevoegen functionaliteit - nog te implementeren')"
        >
            <i class="icon-plus text-xs"></i>
            Toevoegen
        </button>
    </div>

    <!-- Existing Persons List -->
    @if($persons && $persons->count() > 0)
        <div class="space-y-2">
            @foreach($persons as $index => $person)
                <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3 flex-1">
                        <!-- Person Avatar -->
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                            {{ strtoupper(substr($person->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $person->name)[1] ?? '', 0, 1)) }}
                        </div>

                        <!-- Person Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm dark:text-white">
                                    {{ $person->name }}
                                </span>
                            </div>
                            
                            <!-- Organization -->
                            @if($person->organization)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $person->organization->name }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1">
                        <!-- Edit with Lead -->
                        <a 
                            href="{{ route('admin.contacts.persons.edit_with_lead', [$person->id, $leadId]) }}"
                            target="_blank"
                            class="text-green-600 hover:text-green-800 p-1"
                            title="Synchroniseer persoon met lead"
                        >
                            <i class="icon-sync text-sm"></i>
                        </a>

                        <!-- View Person -->
                        <a 
                            href="{{ route('admin.contacts.persons.view', $person->id) }}"
                            target="_blank"
                            class="text-blue-600 hover:text-blue-800 p-1"
                            title="Bekijk persoon"
                        >
                            <i class="icon-eye text-sm"></i>
                        </a>

                        <!-- Remove Person -->
                        <button
                            type="button"
                            class="text-red-600 hover:text-red-800 p-1"
                            title="Verwijder persoon"
                            onclick="detachPerson({{ $person->id }})"
                        >
                            <i class="icon-trash text-sm"></i>
                        </button>
                    </div>

                    <!-- Hidden form fields for existing persons -->
                    <input type="hidden" name="person_ids[{{ $index }}]" value="{{ $person->id }}">
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty state -->
        <div class="text-center py-6 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <i class="icon-users text-3xl mb-2"></i>
            <p class="font-medium">Geen contactpersonen gekoppeld</p>
            <p class="text-sm">Klik op "Toevoegen" om contactpersonen te koppelen</p>
        </div>
    @endif
</div>

@pushOnce('scripts')
<script>
function detachPerson(personId) {
    if (confirm('Weet je zeker dat je deze persoon wilt ontkoppelen van de lead?')) {
        fetch(`/admin/leads/{{ $leadId }}/detach-person/${personId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Reload page to update the list
            } else {
                alert('Er is een fout opgetreden: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Er is een fout opgetreden bij het ontkoppelen van de persoon.');
        });
    }
}
</script>
@endPushOnce

{!! view_render_event('admin.leads.simple_persons.after') !!}