<x-admin::layouts>
    <x-slot:title>
        Duplicaten samenvoegen - {{ $lead->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Hidden form for CSRF token -->
        <form id="csrf-form" style="display: none;">
            @csrf
        </form>

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.leads.view', $lead->id) }}" class="icon-arrow-left text-2xl"></a>
                <h1 class="text-xl font-bold">Duplicaten samenvoegen - Leads</h1>
            </div>
        </div>



        @if($duplicates->count() > 0)
            <!-- Duplicates Management Vue Component -->
            <v-duplicates-manager
                :primary-lead="{{ json_encode($leadData) }}"
                :duplicates="{{ json_encode($duplicatesData) }}"
                merge-url="{{ route('admin.leads.duplicates.merge', $lead->id) }}"
                redirect-url="{{ route('admin.leads.view', $lead->id) }}"
                csrf-token="{{ csrf_token() }}"
            >
                <!-- Loading State -->
                <div class="flex items-center justify-center p-8">
                    <div class="text-center">
                        <div class="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                        <p>Loading duplicates...</p>
                    </div>
                </div>
            </v-duplicates-manager>
        @else
            <!-- No Duplicates Found -->
            <div class="rounded-lg border p-8 text-center dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                    <span class="icon-check text-2xl text-status-active-text"></span>
                </div>
                <h3 class="mb-2 text-lg font-semibold">Geen duplicaten gevonden</h3>
                <p class="text-gray-600">Er zijn geen potentiële dubbele leads gevonden voor deze lead.</p>
                <a href="{{ route('admin.leads.view', $lead->id) }}" class="mt-4 inline-block rounded text-activity-note-text px-4 py-2 text-white hover:bg-blue-700">
                    Back to Lead
                </a>
            </div>
        @endif
    </div>

    @pushOnce('scripts')
        <script>
            // Make CSRF token globally available
            window.csrfToken = '{{ csrf_token() }}';
        </script>

        <script type="text/x-template" id="v-duplicates-manager-template">
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
                        <p class="text-sm text-gray-600">Controleer de redenen per duplicaat en selecteer welke leads je wilt samenvoegen.</p>
                    </div>

                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse table-fixed">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                        <th class="p-3 w-16">ID</th>
                                        <th class="p-3">Naam</th>
                                        <th class="p-3">Fase</th>
                                        <th class="p-3">Aangemaakt op</th>
                                        <th class="p-3">E-mail matches</th>
                                        <th class="p-3">Telefoon matches</th>
                                        <th class="p-3">Naam reden</th>
                                        <th class="p-3 w-24 text-center">Selecteer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ primaryLead.id }}</td>
                                        <td class="p-3 text-sm">@{{ primaryLead.first_name }} @{{ primaryLead.last_name }}</td>
                                        <td class="p-3 text-sm">@{{ primaryLead.stage?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ primaryLead.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryLead.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (primaryLead.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ primaryLead.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" :checked="selectedLeads.includes(primaryLead.id)" disabled />
                                        </td>
                                    </tr>
                                    <tr v-for="duplicate in duplicates" :key="'dup-row-' + duplicate.id" class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3">@{{ duplicate.id }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.first_name }} @{{ duplicate.last_name }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.stage?.name || '-' }}</td>
                                        <td class="p-3 text-sm">@{{ duplicate.created_at || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_emails || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ (duplicate.matched_phones || []).join(', ') || '-' }}</td>
                                        <td class="p-3 text-xs">@{{ duplicate.name_reason || '-' }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" :checked="selectedLeads.includes(duplicate.id)" @change="toggleLeadSelection(duplicate.id)" />
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
                                                    :checked="selectedLeads.includes(primaryLead.id)"
                                                    @change="toggleLeadSelection(primaryLead.id)"
                                                    disabled
                                                    class="mb-2"
                                                />
                                                <span class="text-sm font-medium">Primaire Lead</span>
                                                <span class="text-xs text-gray-500">ID: @{{ primaryLead.id }}</span>
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
                                                    :checked="selectedLeads.includes(duplicate.id)"
                                                    @change="toggleLeadSelection(duplicate.id)"
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

                                            <!-- Primary Lead Column -->
                                            <td class="p-3" :class="fieldConfig.type === 'readonly' ? 'text-center' : ''">
                                                <template v-if="fieldConfig.type === 'readonly'">
                                                    <span class="text-sm text-center break-words">@{{ getFieldValue(primaryLead, fieldConfig) }}</span>
                                                </template>
                                                <template v-else>
                                                    <label class="flex flex-col items-center">
                                                        <input
                                                            type="radio"
                                                            :name="fieldConfig.field"
                                                            :value="primaryLead.id"
                                                            v-model="fieldMappings[fieldConfig.field]"
                                                            class="mb-2"
                                                        />
                                                        <div v-html="renderFieldValue(primaryLead, fieldConfig)"></div>
                                                    </label>
                                                </template>
                                            </td>

                                            <!-- Duplicate Leads Columns -->
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
                                                <p class="text-xs text-status-active-text mt-1">Deze velden hebben dezelfde waarde in alle leads - geen actie vereist</p>
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

                                            <!-- Primary Lead Column -->
                                            <td class="p-3 text-center bg-status-active-bg/50 dark:bg-green-900/20">
                                                <div v-html="renderFieldValue(primaryLead, fieldConfig)"></div>
                                            </td>

                                            <!-- Duplicate Leads Columns -->
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
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Geselecteerd:</span> @{{ selectedLeads.length }} lead(s) voor samenvoegen
                                    <div v-if="selectedLeads.length < 2" class="mt-1 text-xs text-orange-600">
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
                                        @click="mergeLeads"
                                        :disabled="selectedLeads.length < 2 || isLoading"
                                        class="rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                    >
                                        <span v-if="isLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                        <span v-if="isLoading">Samenvoegen...</span>
                                        <span v-else>Samenvoegen geselecteerde leads</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-duplicates-manager', {
                template: '#v-duplicates-manager-template',
                props: ['primaryLead', 'duplicates', 'mergeUrl', 'redirectUrl', 'csrfToken'],
                data() {
                    return {
                        selectedLeads: [this.primaryLead.id], // Primary lead is always selected
                        fieldMappings: {},
                        isLoading: false,
                        showIdenticalFields: false, // Control visibility of identical fields
                        fieldConfigurations: [
                            // Personal Information
                            { field: 'salutation', label: 'Aanhef', type: 'simple' },
                            { field: 'title', label: 'Titel', type: 'simple' },
                            { field: 'first_name', label: 'Voornaam', type: 'simple' },
                            { field: 'last_name', label: 'Achternaam', type: 'simple' },
                            { field: 'lastname_prefix', label: 'Voorvoegsel achternaam', type: 'simple' },
                            { field: 'married_name', label: 'Gehuwde naam', type: 'simple' },
                            { field: 'married_name_prefix', label: 'Voorvoegsel gehuwde naam', type: 'simple' },
                            { field: 'initials', label: 'Initialen', type: 'simple' },
                            { field: 'date_of_birth', label: 'Geboortedatum', type: 'simple' },
                            { field: 'gender', label: 'Geslacht', type: 'simple' },

                            // Pipeline Information (readonly)
                            { field: 'pipeline', label: 'Pipeline', type: 'readonly' },
                            { field: 'stage', label: 'Fase', type: 'readonly' },

                            // Contact Information
                            { field: 'emails', label: 'E-mailadressen', type: 'array' },
                            { field: 'phones', label: 'Telefoonnummers', type: 'array' },
                            { field: 'address', label: 'Adres', type: 'address' },

                            // Lead Information
                            { field: 'status', label: 'Status', type: 'stage' }, // Special handling for status as stage name
                            { field: 'description', label: 'Beschrijving', type: 'simple', cssClass: 'text-sm text-center break-words max-w-xs' },
                            { field: 'lost_reason', label: 'Reden verlies', type: 'simple' },

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
                    console.log('Primary lead:', this.primaryLead);
                    console.log('Duplicates:', this.duplicates);
                    console.log('Field configurations:', this.fieldConfigurations);
                    console.log('Fields with differences:', this.fieldsWithDifferences);
                    console.log('Fields without differences:', this.fieldsWithoutDifferences);
                },
                methods: {
                    initializeFieldMappings() {
                        this.fieldConfigurations.forEach(config => {
                            if (config.type !== 'readonly') {
                                this.fieldMappings[config.field] = this.primaryLead.id;
                            }
                        });
                    },

                    hasFieldDifferences(fieldConfig) {
                        // Skip readonly fields from difference checking
                        if (fieldConfig.type === 'readonly') {
                            return false;
                        }

                        const primaryValue = this.normalizeFieldValue(this.primaryLead, fieldConfig);

                        // Check if any duplicate has a different value
                        return this.duplicates.some(duplicate => {
                            const duplicateValue = this.normalizeFieldValue(duplicate, fieldConfig);
                            return !this.areValuesEqual(primaryValue, duplicateValue, fieldConfig.type);
                        });
                    },

                    normalizeFieldValue(lead, fieldConfig) {
                        const fieldValue = lead[fieldConfig.field];

                        switch (fieldConfig.type) {
                            case 'simple':
                            case 'stage':
                                if (fieldConfig.field === 'status' || fieldConfig.type === 'stage') {
                                    return lead.stage?.name || '';
                                }
                                return fieldValue || '';

                            case 'array':
                                if (!fieldValue || !Array.isArray(fieldValue)) {
                                    return [];
                                }
                                return fieldValue.map(item => item.value || '').sort();

                            case 'address':
                                if (!lead.address) {
                                    return '';
                                }
                                // Create a normalized address string for comparison
                                return [
                                    lead.address.full_address || '',
                                    lead.address.street || '',
                                    lead.address.house_number || '',
                                    lead.address.house_number_suffix || '',
                                    lead.address.postal_code || '',
                                    lead.address.city || '',
                                    lead.address.state || '',
                                    lead.address.country || ''
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

                    toggleLeadSelection(leadId) {
                        if (leadId === this.primaryLead.id) {
                            // Primary lead must always be selected
                            return;
                        }

                        const index = this.selectedLeads.indexOf(leadId);
                        if (index > -1) {
                            this.selectedLeads.splice(index, 1);
                        } else {
                            this.selectedLeads.push(leadId);
                        }
                    },

                    getFieldValue(lead, fieldConfig) {
                        if (fieldConfig.type === 'readonly') {
                            return lead[fieldConfig.field]?.name || 'N/A';
                        }
                        return lead[fieldConfig.field] || 'N/A';
                    },

                    renderFieldValue(lead, fieldConfig) {
                        const cssClass = fieldConfig.cssClass || 'text-sm text-center break-words';

                        switch (fieldConfig.type) {
                            case 'simple':
                                let value = lead[fieldConfig.field] || 'N/A';
                                if (fieldConfig.field === 'description' && typeof value === 'string' && value.length > 100) {
                                    value = value.substring(0, 100) + '…';
                                }
                                return `<span class="${cssClass}">${value}</span>`;

                            case 'stage':
                                const stageName = lead.stage?.name || 'N/A';
                                return `<span class="${cssClass}">${stageName}</span>`;

                            case 'array':
                                if (!lead[fieldConfig.field] || lead[fieldConfig.field].length === 0) {
                                    const emptyText = fieldConfig.field === 'emails' ? 'Geen e-mails' : 'Geen telefoonnummers';
                                    return `<div class="text-xs text-center"><span class="text-gray-400">${emptyText}</span></div>`;
                                }
                                const items = lead[fieldConfig.field].map(item => `<div class="mb-1">${item.value}</div>`).join('');
                                return `<div class="text-xs text-center">${items}</div>`;

                            case 'address':
                                if (!lead.address) {
                                    return '<div class="text-xs text-center"><span class="text-gray-400">Geen adres</span></div>';
                                }
                                let addressHtml = `<div class="text-xs text-center"><div class="mb-1"><div>${lead.address.full_address || 'N/A'}</div>`;
                                if (lead.address.street && lead.address.house_number) {
                                    addressHtml += `<div>${lead.address.street} ${lead.address.house_number}${lead.address.house_number_suffix || ''}</div>`;
                                }
                                if (lead.address.postal_code || lead.address.city) {
                                    addressHtml += `<div>${lead.address.postal_code || ''} ${lead.address.city || ''}</div>`;
                                }
                                if (lead.address.state || lead.address.country) {
                                    addressHtml += `<div>${lead.address.state || ''} ${lead.address.country || ''}</div>`;
                                }
                                addressHtml += '</div></div>';
                                return addressHtml;

                            default:
                                return `<span class="${cssClass}">N/A</span>`;
                        }
                    },

                    async mergeLeads() {
                        if (this.selectedLeads.length < 2) {
                            alert('Selecteer ten minste één duplicaat lead om samen te voegen.');
                            return;
                        }

                        this.isLoading = true;

                        try {
                            const duplicateIds = this.selectedLeads.filter(id => id !== this.primaryLead.id);

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
                                    primary_lead_id: this.primaryLead.id,
                                    duplicate_lead_ids: duplicateIds,
                                    field_mappings: this.fieldMappings,
                                }),
                            });

                            const result = await response.json();

                            if (response.ok) {
                                alert('Leads succesvol samengevoegd!');
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
