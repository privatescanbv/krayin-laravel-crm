<x-admin::layouts>
    <x-slot:title>
        Duplicaten samenvoegen - {{ $person->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Hidden form for CSRF token -->
        <form id="csrf-form" style="display: none;">
            @csrf
        </form>

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="icon-arrow-left text-2xl"></a>
                <h1 class="text-xl font-bold">Duplicaten samenvoegen - Personen</h1>
            </div>
        </div>

        @if($duplicates->count() > 0)
            <!-- Duplicates Management Vue Component -->
            <v-person-duplicates-manager
                :primary-person="{{ json_encode($personData) }}"
                :duplicates="{{ json_encode($duplicatesData) }}"
                merge-url="{{ route('admin.contacts.persons.duplicates.merge', $person->id) }}"
                redirect-url="{{ route('admin.contacts.persons.view', $person->id) }}"
                csrf-token="{{ csrf_token() }}"
            >
                <!-- Loading State -->
                <div class="flex items-center justify-center p-8">
                    <div class="text-center">
                        <div class="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                        <p>Loading duplicates...</p>
                    </div>
                </div>
            </v-person-duplicates-manager>
        @else
            <!-- No Duplicates Found -->
            <div class="rounded-lg border p-8 text-center dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                    <span class="icon-check text-2xl text-succes"></span>
                </div>
                <h3 class="mb-2 text-lg font-semibold">Geen duplicaten gevonden</h3>
                <p class="text-gray-600">Er zijn geen potentiële dubbele personen gevonden voor deze persoon.</p>
                <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="mt-4 inline-block rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Back to Person
                </a>
            </div>
        @endif
    </div>

    @pushOnce('scripts')
        <script>
            // Make CSRF token globally available
            window.csrfToken = '{{ csrf_token() }}';
        </script>

        <script type="text/x-template" id="v-person-duplicates-manager-template">
            <div class="flex flex-col gap-4">
                <!-- Duplicates Summary Block -->
                <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="text-lg font-semibold text-orange-600">
                                Mogelijke duplicaten (@{{ duplicates.length }})
                            </h3>
                            <div class="relative group">
                                <span class="icon-info rounded-full bg-activity-task-bg text-blue-600 dark:!text-blue-600 cursor-help text-sm"></span>
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-blue-600 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-10 shadow-lg">
                                    <div class="font-medium mb-1">Hoe worden duplicaten gevonden?</div>
                                    <div class="">
                                        • <strong>E-mailadressen:</strong> Exacte match van e-mailadressen<br>
                                        • <strong>Telefoonnummers:</strong> Exacte match van telefoonnummers (genormaliseerd)<br>
                                        • <strong>Namen:</strong> Voornaam + achternaam combinatie<br>
                                        • <strong>Gehuwde naam:</strong> Wordt ook meegenomen bij naam matching
                                    </div>
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-blue-600"></div>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600">Controleer de redenen per duplicaat en selecteer welke personen je wilt samenvoegen.</p>
                    </div>

                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse table-fixed">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                        <th class="p-3 w-16">ID</th>
                                        <th class="p-3">Naam</th>
                                        <th class="p-3">Organisatie</th>
                                        <th class="p-3">Aangemaakt op</th>
                                        <th class="p-3">E-mail matches</th>
                                        <th class="p-3">Telefoon matches</th>
                                        <th class="p-3">Naam reden</th>
                                        <th class="p-3 w-24 text-center">Selecteer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ primaryPerson.id }}</td>
                                        <td class="p-3 text-sm">@{{ primaryPerson.first_name }} @{{ primaryPerson.last_name }}</td>
                                        <td class="p-3 text-sm">@{{ primaryPerson.organization?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ primaryPerson.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryPerson.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryPerson.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ primaryPerson.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" :checked="selectedPersons.includes(primaryPerson.id)" disabled />
                                        </td>
                                    </tr>
                                    <tr v-for="duplicate in duplicates" :key="'dup-row-' + duplicate.id" class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ duplicate.id }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.first_name }} @{{ duplicate.last_name }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.organization?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ duplicate.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" :checked="selectedPersons.includes(duplicate.id)" @change="togglePersonSelection(duplicate.id)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Field Differences Block -->
                <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-blue-700">Velden met verschillen</h3>
                        <p class="text-sm text-gray-600">Kies per veld welke waarde behouden moet blijven.</p>
                    </div>

                    <div class="p-4">
                        <!-- Field Comparison Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse table-fixed">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="w-32 p-3 text-left font-semibold">Veld</th>
                                        <th class="p-3 text-center text-succes min-w-48">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedPersons.includes(primaryPerson.id)"
                                                    @change="togglePersonSelection(primaryPerson.id)"
                                                    disabled
                                                    class="mb-2"
                                                />
                                                <span class="text-sm font-medium">Primaire Persoon</span>
                                                <span class="text-xs text-gray-500">ID: @{{ primaryPerson.id }}</span>
                                            </div>
                                        </th>
                                        <th
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3 text-center text-orange-600 min-w-48"
                                        >
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedPersons.includes(duplicate.id)"
                                                    @change="togglePersonSelection(duplicate.id)"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm font-medium">Duplicaat</span>
                                                <span class="text-xs text-gray-500">ID: @{{ duplicate.id }}</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Fields with Differences Section -->
                                    <template v-if="fieldsWithDifferences.length > 0">
                                        <tr class="bg-orange-50 dark:bg-orange-900/20">
                                            <td :colspan="2 + duplicates.length" class="p-4 text-center border-b border-orange-200 dark:border-orange-800">
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="icon-alert text-orange-600 text-lg"></span>
                                                    <h4 class="text-sm font-semibold text-orange-700 dark:text-orange-400">
                                                        Velden met verschillen (@{{ fieldsWithDifferences.length }})
                                                    </h4>
                                                </div>
                                                <p class="text-xs text-orange-600 mt-1">Deze velden hebben verschillende waarden - selecteer welke waarde behouden moet blijven</p>
                                            </td>
                                        </tr>
                                    </template>

                                    <template v-for="fieldConfig in fieldsWithDifferences" :key="'diff-' + fieldConfig.field">
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">
                                                @{{ fieldConfig.label }}
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-activity-note-text">
                                                    Verschil
                                                </span>
                                            </td>

                                            <!-- Primary Person Column -->
                                            <td class="p-3" :class="fieldConfig.type === 'readonly' ? 'text-center' : ''">
                                                <template v-if="fieldConfig.type === 'readonly'">
                                                    <span class="text-sm text-center break-words">@{{ getFieldValue(primaryPerson, fieldConfig) }}</span>
                                                </template>
                                                <template v-else>
                                                    <label class="flex flex-col items-center">
                                                        <input
                                                            type="radio"
                                                            :name="fieldConfig.field"
                                                            :value="primaryPerson.id"
                                                            v-model="fieldMappings[fieldConfig.field]"
                                                            class="mb-2"
                                                        />
                                                        <div v-html="renderFieldValue(primaryPerson, fieldConfig)"></div>
                                                    </label>
                                                </template>
                                            </td>

                                            <!-- Duplicate Persons Columns -->
                                            <td
                                                v-for="duplicate in duplicates"
                                                :key="duplicate.id"
                                                class="p-3"
                                                :class="fieldConfig.type === 'readonly' ? 'text-center' : ''"
                                            >
                                                <template v-if="fieldConfig.type === 'readonly'">
                                                    <span class="text-sm text-center break-words">@{{ getFieldValue(duplicate, fieldConfig) }}</span>
                                                </template>
                                                <template v-else>
                                                    <label class="flex flex-col items-center">
                                                        <input
                                                            type="radio"
                                                            :name="fieldConfig.field"
                                                            :value="duplicate.id"
                                                            v-model="fieldMappings[fieldConfig.field]"
                                                            class="mb-2"
                                                        />
                                                        <div v-html="renderFieldValue(duplicate, fieldConfig)"></div>
                                                    </label>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Section Divider for Identical Fields -->
                                    <template v-if="fieldsWithoutDifferences.length > 0">
                                        <tr class="bg-green-50 dark:bg-green-900/20">
                                            <td :colspan="2 + duplicates.length" class="p-4 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="icon-check text-succes text-lg"></span>
                                                    <h4 class="text-sm font-semibold text-green-700 dark:text-green-400">
                                                        Velden zonder verschillen (@{{ fieldsWithoutDifferences.length }})
                                                    </h4>
                                                    <button
                                                        @click="showIdenticalFields = !showIdenticalFields"
                                                        class="ml-2 text-xs text-succes hover:text-green-800 underline"
                                                    >
                                                        @{{ showIdenticalFields ? 'Verbergen' : 'Tonen voor controle' }}
                                                    </button>
                                                </div>
                                                <p class="text-xs text-succes mt-1">Deze velden hebben dezelfde waarde in alle personen - geen actie vereist</p>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Fields without Differences Section (Collapsible) -->
                                    <template v-if="showIdenticalFields" v-for="fieldConfig in fieldsWithoutDifferences" :key="'same-' + fieldConfig.field">
                                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-green-50/30 dark:bg-green-900/10">
                                            <td class="p-3 font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                                @{{ fieldConfig.label }}
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-activity-email-bg text-green-800">
                                                    Identiek
                                                </span>
                                            </td>

                                            <!-- Primary Person Column -->
                                            <td class="p-3 text-center bg-green-50/50 dark:bg-green-900/20">
                                                <div v-html="renderFieldValue(primaryPerson, fieldConfig)"></div>
                                            </td>

                                            <!-- Duplicate Persons Columns -->
                                            <td
                                                v-for="duplicate in duplicates"
                                                :key="duplicate.id"
                                                class="p-3 text-center bg-green-50/50 dark:bg-green-900/20"
                                            >
                                                <div v-html="renderFieldValue(duplicate, fieldConfig)"></div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Geselecteerd:</span> @{{ selectedPersons.length }} persoon/personen voor samenvoegen
                                    <div v-if="selectedPersons.length < 2" class="mt-1 text-xs text-orange-600">
                                        Selecteer ten minste één duplicaat om samen te voegen
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <a
                                        :href="redirectUrl"
                                        class="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Annuleren
                                    </a>
                                    <button
                                        @click="mergePersons"
                                        :disabled="selectedPersons.length < 2 || isLoading"
                                        class="rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                    >
                                        <span v-if="isLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                        <span v-if="isLoading">Samenvoegen...</span>
                                        <span v-else>Samenvoegen geselecteerde personen</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-person-duplicates-manager', {
                template: '#v-person-duplicates-manager-template',
                props: ['primaryPerson', 'duplicates', 'mergeUrl', 'redirectUrl', 'csrfToken'],
                data() {
                    return {
                        selectedPersons: [this.primaryPerson.id], // Primary person is always selected
                        fieldMappings: {},
                        isLoading: false,
                        showIdenticalFields: false, // Control visibility of identical fields
                        fieldConfigurations: [
                            // Personal Information
                            { field: 'salutation', label: 'Aanhef', type: 'simple' },
                            { field: 'first_name', label: 'Voornaam', type: 'simple' },
                            { field: 'last_name', label: 'Achternaam', type: 'simple' },
                            { field: 'lastname_prefix', label: 'Voorvoegsel achternaam', type: 'simple' },
                            { field: 'married_name', label: 'Gehuwde naam', type: 'simple' },
                            { field: 'married_name_prefix', label: 'Voorvoegsel gehuwde naam', type: 'simple' },
                            { field: 'initials', label: 'Initialen', type: 'simple' },
                            { field: 'date_of_birth', label: 'Geboortedatum', type: 'simple' },
                            { field: 'gender', label: 'Geslacht', type: 'simple' },
                            { field: 'job_title', label: 'Functie', type: 'simple' },

                            // Organization Information (readonly)
                            { field: 'organization', label: 'Organisatie', type: 'readonly' },

                            // Contact Information
                            { field: 'emails', label: 'E-mailadressen', type: 'array' },
                            { field: 'phones', label: 'Telefoonnummers', type: 'array' },
                            { field: 'address', label: 'Adres', type: 'address' },
                        ]
                    };
                },
                computed: {
                    fieldsWithDifferences() {
                        return this.fieldConfigurations.filter(config => {
                            return this.hasFieldDifferences(config);
                        });
                    },
                    fieldsWithoutDifferences() {
                        return this.fieldConfigurations.filter(config => {
                            return !this.hasFieldDifferences(config);
                        });
                    }
                },
                mounted() {
                    // Initialize field mappings
                    this.initializeFieldMappings();

                    // Debug logging
                    console.log('Primary person:', this.primaryPerson);
                    console.log('Duplicates:', this.duplicates);
                    console.log('Field configurations:', this.fieldConfigurations);
                    console.log('Fields with differences:', this.fieldsWithDifferences);
                    console.log('Fields without differences:', this.fieldsWithoutDifferences);
                },
                methods: {
                    initializeFieldMappings() {
                        this.fieldConfigurations.forEach(config => {
                            if (config.type !== 'readonly') {
                                this.fieldMappings[config.field] = this.primaryPerson.id;
                            }
                        });
                    },

                    hasFieldDifferences(fieldConfig) {
                        // Skip readonly fields from difference checking
                        if (fieldConfig.type === 'readonly') {
                            return false;
                        }

                        const primaryValue = this.normalizeFieldValue(this.primaryPerson, fieldConfig);

                        // Check if any duplicate has a different value
                        return this.duplicates.some(duplicate => {
                            const duplicateValue = this.normalizeFieldValue(duplicate, fieldConfig);
                            return !this.areValuesEqual(primaryValue, duplicateValue, fieldConfig.type);
                        });
                    },

                    normalizeFieldValue(person, fieldConfig) {
                        const fieldValue = person[fieldConfig.field];

                        switch (fieldConfig.type) {
                            case 'simple':
                                return fieldValue || '';

                            case 'array':
                                if (!fieldValue || !Array.isArray(fieldValue)) {
                                    return [];
                                }
                                return fieldValue.map(item => item.value || '').sort();

                            case 'address':
                                if (!person.address) {
                                    return '';
                                }
                                // Create a normalized address string for comparison
                                return [
                                    person.address.full_address || '',
                                    person.address.street || '',
                                    person.address.house_number || '',
                                    person.address.house_number_suffix || '',
                                    person.address.postal_code || '',
                                    person.address.city || '',
                                    person.address.state || '',
                                    person.address.country || ''
                                ].join('|');

                            default:
                                return fieldValue || '';
                        }
                    },

                    areValuesEqual(value1, value2, fieldType) {
                        if (fieldType === 'array') {
                            // Compare arrays
                            if (value1.length !== value2.length) {
                                return false;
                            }
                            return value1.every((item, index) => item === value2[index]);
                        }

                        // Compare strings/primitives
                        return value1 === value2;
                    },

                    togglePersonSelection(personId) {
                        if (personId === this.primaryPerson.id) {
                            // Primary person must always be selected
                            return;
                        }

                        const index = this.selectedPersons.indexOf(personId);
                        if (index > -1) {
                            this.selectedPersons.splice(index, 1);
                        } else {
                            this.selectedPersons.push(personId);
                        }
                    },

                    getFieldValue(person, fieldConfig) {
                        if (fieldConfig.type === 'readonly') {
                            return person[fieldConfig.field]?.name || 'N/A';
                        }
                        return person[fieldConfig.field] || 'N/A';
                    },

                    renderFieldValue(person, fieldConfig) {
                        const cssClass = fieldConfig.cssClass || 'text-sm text-center break-words';

                        switch (fieldConfig.type) {
                            case 'simple':
                                let value = person[fieldConfig.field] || 'N/A';
                                return `<span class="${cssClass}">${value}</span>`;

                            case 'array':
                                if (!person[fieldConfig.field] || person[fieldConfig.field].length === 0) {
                                    const emptyText = fieldConfig.field === 'emails' ? 'Geen e-mails' : 'Geen telefoonnummers';
                                    return `<div class="text-xs text-center"><span class="text-gray-400">${emptyText}</span></div>`;
                                }
                                const items = person[fieldConfig.field].map(item => `<div class="mb-1">${item.value}</div>`).join('');
                                return `<div class="text-xs text-center">${items}</div>`;

                            case 'address':
                                if (!person.address) {
                                    return '<div class="text-xs text-center"><span class="text-gray-400">Geen adres</span></div>';
                                }
                                let addressHtml = `<div class="text-xs text-center"><div class="mb-1"><div>${person.address.full_address || 'N/A'}</div>`;
                                if (person.address.street && person.address.house_number) {
                                    addressHtml += `<div>${person.address.street} ${person.address.house_number}${person.address.house_number_suffix || ''}</div>`;
                                }
                                if (person.address.postal_code || person.address.city) {
                                    addressHtml += `<div>${person.address.postal_code || ''} ${person.address.city || ''}</div>`;
                                }
                                if (person.address.state || person.address.country) {
                                    addressHtml += `<div>${person.address.state || ''} ${person.address.country || ''}</div>`;
                                }
                                addressHtml += '</div></div>';
                                return addressHtml;

                            default:
                                return `<span class="${cssClass}">N/A</span>`;
                        }
                    },

                    async mergePersons() {
                        if (this.selectedPersons.length < 2) {
                            alert('Selecteer ten minste één duplicaat persoon om samen te voegen.');
                            return;
                        }

                        this.isLoading = true;

                        try {
                            const duplicateIds = this.selectedPersons.filter(id => id !== this.primaryPerson.id);

                            // Get CSRF token
                            let csrfToken = this.csrfToken;
                            if (!csrfToken) {
                                const metaToken = document.querySelector('meta[name="csrf-token"]');
                                if (metaToken) {
                                    csrfToken = metaToken.getAttribute('content');
                                } else {
                                    const formToken = document.querySelector('#csrf-form input[name="_token"]');
                                    if (formToken) {
                                        csrfToken = formToken.value;
                                    }
                                }
                            }

                            const response = await fetch(this.mergeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    primary_person_id: this.primaryPerson.id,
                                    duplicate_person_ids: duplicateIds,
                                    field_mappings: this.fieldMappings,
                                }),
                            });

                            const result = await response.json();

                            if (response.ok) {
                                alert('Personen succesvol samengevoegd!');
                                window.location.href = this.redirectUrl;
                            } else {
                                throw new Error(result.message || 'Er is een fout opgetreden bij het samenvoegen.');
                            }
                        } catch (error) {
                            console.error('Merge error:', error);
                            alert('Er is een fout opgetreden: ' + error.message);
                        } finally {
                            this.isLoading = false;
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
