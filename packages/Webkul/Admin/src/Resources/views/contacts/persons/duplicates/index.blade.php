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
                false-positive-url="{{ route('admin.contacts.persons.duplicates.false_positive', $person->id) }}"
                redirect-url="{{ route('admin.contacts.persons.view', $person->id) }}"
                person-view-url="{{ route('admin.contacts.persons.view', ['id' => '__ID__']) }}"
                duplicates-index-url="{{ route('admin.contacts.persons.duplicates.index', ['id' => '__ID__']) }}"
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
                    <span class="icon-check text-2xl text-status-active-text"></span>
                </div>
                <h3 class="mb-2 text-lg font-semibold">Geen duplicaten gevonden</h3>
                <p class="text-gray-600">Er zijn geen potentiële dubbele personen gevonden voor deze persoon.</p>
                <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="mt-4 inline-block rounded text-activity-note-text px-4 py-2 text-white hover:bg-blue-700">
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
                                <span class="icon-info rounded-full bg-activity-task-bg text-activity-note-text dark:!text-activity-note-text cursor-help text-sm"></span>
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-activity-note-text text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-10 shadow-lg">
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
                        <p class="text-sm text-gray-600">Controleer de redenen per duplicaat en selecteer welke personen je wilt samenvoegen. Een persoon met een patiëntportaal kan niet als duplicaat worden samengevoegd.</p>
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
                                        <th class="p-3 w-40 text-center">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ primaryPerson.id }}</td>
                                        <td class="p-3 text-sm">
                                            @{{ primaryPerson.first_name }} @{{ primaryPerson.last_name }}
                                            <span class="ml-1 inline-flex items-center rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-800">Primair</span>
                                            <span
                                                v-if="primaryPerson.has_portal_account"
                                                class="ml-1 inline-flex items-center rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800"
                                                title="Deze persoon heeft een patiëntportaalaccount. Dat account blijft op de primaire persoon staan."
                                            >portaal</span>
                                        </td>
                                        <td class="p-3 text-sm">@{{ primaryPerson.organization?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ primaryPerson.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryPerson.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryPerson.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ primaryPerson.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" :checked="selectedPersons.includes(primaryPerson.id)" disabled />
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center justify-center gap-2">
                                                <a
                                                    :href="personHref(primaryPerson.id)"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="icon-eye text-xl text-gray-600 hover:text-blue-700"
                                                    title="Bekijk persoon"
                                                ></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-for="duplicate in duplicates" :key="'dup-row-' + duplicate.id" class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ duplicate.id }}</td>
                                        <td class="p-3 text-sm">
                                            @{{ duplicate.first_name }} @{{ duplicate.last_name }}
                                            <span
                                                v-if="duplicate.has_portal_account"
                                                class="ml-1 inline-flex items-center rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800"
                                                title="Deze persoon heeft een patiëntportaalaccount. Maak deze persoon primair, of trek het account eerst in."
                                            >portaal</span>
                                        </td>
                                        <td class="p-3 text-sm">@{{ duplicate.organization?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ duplicate.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="selectedPersons.includes(duplicate.id)"
                                                :title="duplicate.has_portal_account ? 'Heeft een patiëntportaalaccount - kan alleen als geen duplicaat worden gemarkeerd, niet worden samengevoegd.' : ''"
                                                @change="togglePersonSelection(duplicate.id)"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center justify-center gap-2">
                                                <a
                                                    :href="personHref(duplicate.id)"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="icon-eye text-xl text-gray-600 hover:text-blue-700"
                                                    title="Bekijk persoon"
                                                ></a>
                                                <a
                                                    :href="makePrimaryHref(duplicate.id)"
                                                    class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                    title="Deze persoon als primaire kiezen"
                                                >
                                                    Maak primair
                                                </a>
                                            </div>
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
                                        <th class="p-3 text-center text-status-active-text min-w-48">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedPersons.includes(primaryPerson.id)"
                                                    @change="togglePersonSelection(primaryPerson.id)"
                                                    disabled
                                                    class="mb-2"
                                                />
                                                <span class="text-sm font-medium">Primaire Persoon</span>
                                                <span
                                                    v-if="primaryPerson.has_portal_account"
                                                    class="mt-1 inline-flex items-center rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800"
                                                >portaal</span>
                                                <span class="text-xs text-gray-500">ID:
                                                   <a
                                                       :href="personHref(primaryPerson.id)"
                                                       target="_blank"
                                                       rel="noopener"
                                                   >
                                                        @{{ primaryPerson.id }}
                                                    </a>
                                                </span>
                                                <a
                                                    :href="personHref(primaryPerson.id)"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="icon-eye mt-1 text-xl text-gray-600 hover:text-blue-700"
                                                    title="Bekijk persoon"
                                                ></a>
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
                                                    :title="duplicate.has_portal_account ? 'Heeft een patiëntportaalaccount - kan alleen als geen duplicaat worden gemarkeerd, niet worden samengevoegd.' : ''"
                                                    @change="togglePersonSelection(duplicate.id)"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm font-medium">Duplicaat</span>
                                                <span
                                                    v-if="duplicate.has_portal_account"
                                                    class="mt-1 inline-flex items-center rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800"
                                                >portaal</span>
                                                <span class="text-xs text-gray-500">ID:
                                                    <a
                                                        :href="personHref(duplicate.id)"
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        @{{ duplicate.id }}
                                                    </a>
                                                </span>
                                                <div class="mt-2 flex items-center justify-center gap-2">
                                                    <a
                                                        :href="personHref(duplicate.id)"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="icon-eye text-xl text-gray-600 hover:text-blue-700"
                                                        title="Bekijk persoon"
                                                    ></a>
                                                    <a
                                                        :href="makePrimaryHref(duplicate.id)"
                                                        class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                        title="Deze persoon als primaire kiezen"
                                                    >
                                                        Maak primair
                                                    </a>
                                                </div>
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
                                        <tr class="bg-status-active-bg dark:bg-green-900/20">
                                            <td :colspan="2 + duplicates.length" class="p-4 text-center">
                                                <div class="flex items-center justify-center gap-2">

                                                    <h4 class="text-sm font-semibold text-green-700 dark:text-green-400">
                                                        Velden zonder verschillen (@{{ fieldsWithoutDifferences.length }})
                                                    </h4>
                                                    <button
                                                        @click="showIdenticalFields = !showIdenticalFields"
                                                        class="ml-2 text-xs text-status-active-text hover:text-green-800 underline"
                                                    >
                                                        @{{ showIdenticalFields ? 'Verbergen' : 'Tonen voor controle' }}
                                                    </button>
                                                </div>
                                                <p class="text-xs text-status-active-text mt-1">Deze velden hebben dezelfde waarde in alle personen - geen actie vereist</p>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Fields without Differences Section (Collapsible) -->
                                    <template v-if="showIdenticalFields" v-for="fieldConfig in fieldsWithoutDifferences" :key="'same-' + fieldConfig.field">
                                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-status-active-bg/30 dark:bg-green-900/10">
                                            <td class="p-3 font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                                @{{ fieldConfig.label }}
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-activity-email-bg text-green-800">
                                                    Identiek
                                                </span>
                                            </td>

                                            <!-- Primary Person Column -->
                                            <td class="p-3 text-center bg-status-active-bg/50 dark:bg-green-900/20">
                                                <div v-html="renderFieldValue(primaryPerson, fieldConfig)"></div>
                                            </td>

                                            <!-- Duplicate Persons Columns -->
                                            <td
                                                v-for="duplicate in duplicates"
                                                :key="duplicate.id"
                                                class="p-3 text-center bg-status-active-bg/50 dark:bg-green-900/20"
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
                            <div v-if="portalDuplicates.length > 0" class="mb-4 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100">
                                <p>
                                    Personen met een patiëntportaal kunnen niet als duplicaat worden samengevoegd, maar wel als geen duplicaat worden gemarkeerd. Om samen te voegen: maak die persoon primair, of trek het account eerst met de hand in.
                                </p>
                                <p v-if="bothSidesHavePortal" class="mt-1 font-medium">
                                    Er zijn meerdere portaalaccounts. Trek er eerst één in voordat je samenvoegt.
                                </p>
                            </div>
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
                                        @click="markAsFalsePositive"
                                        :disabled="selectedPersons.length < 2 || isLoading"
                                        class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Markeren als geen duplicaat
                                    </button>
                                    <button
                                        @click="mergePersons"
                                        :disabled="!canMerge || isLoading"
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
                props: ['primaryPerson', 'duplicates', 'mergeUrl', 'falsePositiveUrl', 'redirectUrl', 'personViewUrl', 'duplicatesIndexUrl'],
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
                            { field: 'national_identification_number', label: 'BSN', type: 'simple' },
                            { field: 'preferred_language', label: 'Taalvoorkeur', type: 'simple', displayField: 'preferred_language_label' },
                            { field: 'is_active', label: 'Actief/inactief', type: 'simple', displayField: 'is_active_label' },

                            // Organization (stored as id; displayField only changes what is shown)
                            { field: 'organization_id', label: 'Organisatie', type: 'simple', displayField: 'organization_name' },

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
                    },
                    portalDuplicates() {
                        return this.duplicates.filter(duplicate => duplicate.has_portal_account);
                    },
                    bothSidesHavePortal() {
                        return !!this.primaryPerson.has_portal_account && this.portalDuplicates.length > 0;
                    },
                    canMerge() {
                        if (this.selectedPersons.length < 2) {
                            return false;
                        }

                        return !this.selectedPersons.some(id => id !== this.primaryPerson.id && this.personHasPortal(id));
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
                                // Booleans (is_active): keep false distinct from empty.
                                if (typeof fieldValue === 'boolean') {
                                    return fieldValue;
                                }
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
                            if (value1.length !== value2.length) {
                                return false;
                            }
                            return value1.every((item, index) => item === value2[index]);
                        }

                        return value1 === value2;
                    },

                    togglePersonSelection(personId) {
                        if (personId === this.primaryPerson.id) {
                            return;
                        }

                        const index = this.selectedPersons.indexOf(personId);
                        if (index > -1) {
                            this.selectedPersons.splice(index, 1);
                        } else {
                            this.selectedPersons.push(personId);
                        }
                    },

                    personHasPortal(personId) {
                        if (personId === this.primaryPerson.id) {
                            return !!this.primaryPerson.has_portal_account;
                        }

                        const duplicate = this.duplicates.find(item => item.id === personId);

                        return !!(duplicate && duplicate.has_portal_account);
                    },

                    personHref(personId) {
                        return this.personViewUrl.replace('__ID__', personId);
                    },

                    makePrimaryHref(personId) {
                        return this.duplicatesIndexUrl.replace('__ID__', personId);
                    },

                    getFieldValue(person, fieldConfig) {
                        if (fieldConfig.type === 'readonly') {
                            return person[fieldConfig.field]?.name || 'N/A';
                        }
                        return person[fieldConfig.displayField ?? fieldConfig.field] || 'N/A';
                    },

                    // Values end up in v-html; escape free text (BSN, names, addresses).
                    esc(value) {
                        const div = document.createElement('div');
                        div.textContent = value ?? '';
                        return div.innerHTML;
                    },

                    renderFieldValue(person, fieldConfig) {
                        const cssClass = fieldConfig.cssClass || 'text-sm text-center break-words';

                        switch (fieldConfig.type) {
                            case 'simple':
                                let value = person[fieldConfig.displayField ?? fieldConfig.field];
                                if (value === null || value === undefined || value === '') {
                                    value = 'N/A';
                                }
                                return `<span class="${cssClass}">${this.esc(String(value))}</span>`;

                            case 'array':
                                if (!person[fieldConfig.field] || person[fieldConfig.field].length === 0) {
                                    const emptyText = fieldConfig.field === 'emails' ? 'Geen e-mails' : 'Geen telefoonnummers';
                                    return `<div class="text-xs text-center"><span class="text-gray-400">${emptyText}</span></div>`;
                                }
                                const items = person[fieldConfig.field].map(item => `<div class="mb-1">${this.esc(item.value)}</div>`).join('');
                                return `<div class="text-xs text-center">${items}</div>`;

                            case 'address':
                                if (!person.address) {
                                    return '<div class="text-xs text-center"><span class="text-gray-400">Geen adres</span></div>';
                                }
                                let addressHtml = `<div class="text-xs text-center"><div class="mb-1"><div>${this.esc(person.address.full_address || 'N/A')}</div>`;
                                if (person.address.street && person.address.house_number) {
                                    addressHtml += `<div>${this.esc(person.address.street)} ${this.esc(person.address.house_number)}${this.esc(person.address.house_number_suffix || '')}</div>`;
                                }
                                if (person.address.postal_code || person.address.city) {
                                    addressHtml += `<div>${this.esc(person.address.postal_code || '')} ${this.esc(person.address.city || '')}</div>`;
                                }
                                if (person.address.state || person.address.country) {
                                    addressHtml += `<div>${this.esc(person.address.state || '')} ${this.esc(person.address.country || '')}</div>`;
                                }
                                addressHtml += '</div></div>';
                                return addressHtml;

                            default:
                                return `<span class="${cssClass}">N/A</span>`;
                        }
                    },

                    async mergePersons() {
                        if (!this.canMerge) {
                            alert('Selecteer ten minste één duplicaat zonder patiëntportaal om samen te voegen.');
                            return;
                        }

                        this.isLoading = true;

                        try {
                            const duplicateIds = this.selectedPersons.filter(id => id !== this.primaryPerson.id);
                            const result = await window.privatescan.runJsonAction(
                                this.mergeUrl,
                                {
                                    primary_person_id: this.primaryPerson.id,
                                    duplicate_person_ids: duplicateIds,
                                    field_mappings: this.fieldMappings,
                                },
                                {
                                    successMessage: 'Personen succesvol samengevoegd!',
                                    onSuccess: 'redirect',
                                    redirectUrl: this.redirectUrl,
                                    errorPrefix: 'Samenvoegen mislukt.',
                                }
                            );

                            if (!result.ok) {
                                return;
                            }
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    async markAsFalsePositive() {
                        if (this.selectedPersons.length < 2) {
                            alert('Selecteer ten minste twee personen om te markeren als geen duplicaat.');
                            return;
                        }

                        this.isLoading = true;

                        try {
                            const result = await window.privatescan.runJsonAction(
                                this.falsePositiveUrl,
                                {
                                    entity_ids: this.selectedPersons,
                                },
                                {
                                    confirmText: 'Weet je zeker dat je de geselecteerde personen wilt markeren als geen duplicaat? Ze worden daarna niet meer als duplicaat getoond.',
                                    successMessage: 'Gemarkeerd als geen duplicaat.',
                                    onSuccess: 'reload',
                                    errorPrefix: 'Markeren als geen duplicaat mislukt.',
                                }
                            );

                            if (!result.ok) {
                                return;
                            }
                        } finally {
                            this.isLoading = false;
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
