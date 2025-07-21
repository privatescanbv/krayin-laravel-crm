<x-admin::layouts>
    <x-slot:title>
        Person bijwerken met Lead gegevens
    </x-slot>

    {!! view_render_event('admin.contacts.persons.edit_with_lead.header.before', ['person' => $person, 'lead' => $lead]) !!}

    <!-- Page Header -->
    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="flex flex-col gap-2">
            <div class="flex cursor-pointer items-center">
                <x-admin::breadcrumbs name="contacts.persons.edit_with_lead" :entity="$person" />
            </div>

            <div class="text-xl font-bold dark:text-white">
                Person bijwerken met Lead gegevens
            </div>
            
            <p class="text-gray-600 dark:text-gray-300">
                Vergelijk en synchroniseer gegevens tussen 
                <strong>{{ $person->name }}</strong> en Lead <strong>{{ $lead->title }}</strong>
            </p>
        </div>

        <div class="flex items-center gap-x-2.5">
            <a 
                href="{{ route('admin.contacts.persons.view', $person->id) }}" 
                class="secondary-button"
            >
                @lang('admin::app.account.edit.back-btn')
            </a>
        </div>
    </div>

    {!! view_render_event('admin.contacts.persons.edit_with_lead.header.after', ['person' => $person, 'lead' => $lead]) !!}

    <form id="person-lead-update-form" action="{{ route('admin.contacts.persons.update_with_lead', [$person->id, $lead->id]) }}" method="POST">
        @csrf

        <div class="mt-3.5">
            @if(empty($fieldDifferences))
                <div class="flex flex-col items-center justify-center py-16">
                    <div class="text-6xl text-green-500 mb-4">
                        <i class="icon-check-circle"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 dark:text-white">Geen verschillen gevonden</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-center max-w-md">
                        Alle vergelijkbare velden tussen de person en lead hebben dezelfde waarden.
                    </p>
                </div>
            @else
                <div class="box-shadow rounded bg-white dark:bg-gray-900">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-lg font-semibold dark:text-white">Veld Verschillen</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                            Selecteer welke velden je wilt bijwerken. Je kunt ook de lead waarden aanpassen voordat je opslaat.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="w-12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Update
                                    </th>
                                    <th class="w-48 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Veld
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Person Waarde
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Lead Waarde
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach($fieldDifferences as $field => $difference)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-4">
                                            <input 
                                                type="checkbox" 
                                                name="person_updates[{{ $field }}]" 
                                                value="1"
                                                id="update_{{ $field }}"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                            >
                                        </td>
                                        <td class="px-4 py-4">
                                            <label for="update_{{ $field }}" class="font-medium text-gray-900 dark:text-white cursor-pointer">
                                                {{ $difference['label'] }}
                                            </label>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                                {{ $difference['person_value'] ?: 'Geen waarde' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            @if($difference['type'] === 'array')
                                                <div class="text-sm text-blue-600 dark:text-blue-400">
                                                    {{ $difference['lead_value'] ?: 'Geen waarde' }}
                                                </div>
                                                <input type="hidden" name="lead_updates[{{ $field }}]" value="{{ $difference['lead_value'] }}">
                                            @else
                                                <input 
                                                    type="text" 
                                                    name="lead_updates[{{ $field }}]" 
                                                    value="{{ $difference['lead_value'] }}"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    placeholder="Geen waarde"
                                                >
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 box-shadow rounded bg-white dark:bg-gray-900 p-4">
                    <div class="flex justify-between items-center">
                        <div class="flex gap-2">
                            <button type="button" id="select-all" class="secondary-button">
                                Alles selecteren
                            </button>
                            <button type="button" id="select-none" class="secondary-button">
                                Niets selecteren
                            </button>
                        </div>
                        
                        <div>
                            <button type="submit" class="primary-button">
                                @lang('admin::app.account.edit.save-btn')
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

    {!! view_render_event('admin.contacts.persons.edit_with_lead.content.after', ['person' => $person, 'lead' => $lead]) !!}
</x-admin::layouts>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Select all/none functionality
                const selectAllBtn = document.getElementById('select-all');
                const selectNoneBtn = document.getElementById('select-none');
                const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="person_updates"]');

                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', function() {
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = true;
                        });
                    });
                }

                if (selectNoneBtn) {
                    selectNoneBtn.addEventListener('click', function() {
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                    });
                }

                // Form submission
                const form = document.getElementById('person-lead-update-form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();

                        const formData = new FormData(form);
                        const submitBtn = form.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;

                        // Show loading state
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = 'Bezig met opslaan...';

                        fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                // Show success message
                                alert(data.message);

                                // Redirect if URL provided
                                if (data.redirect_url) {
                                    setTimeout(() => {
                                        window.location.href = data.redirect_url;
                                    }, 1000);
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Er is een fout opgetreden bij het opslaan.');
                        })
                        .finally(() => {
                            // Restore button state
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                    });
                }
            });
        </script>
    @endpush