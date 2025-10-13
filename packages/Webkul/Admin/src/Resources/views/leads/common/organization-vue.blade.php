{!! view_render_event('admin.leads.organization.before') !!}

<!-- Lead Organization Section -->
<div id="organization" class="flex flex-col gap-4">
    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.organization.title')
            </x-admin::form.control-group.label>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                <i>Koppel een organisatie voor facturatie doeleinden (optioneel)</i>
            </p>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-4">
            <!-- Organization Lookup -->
            <div class="mb-4">
                <x-admin::lookup
                    src="{{ route('admin.contacts.organizations.search') }}"
                    name="organization_lookup"
                    label="Naam"
                    :value="selectedOrganization"
                    placeholder="Zoek organisatie..."
                    :can-add-new="false"
                    @on-selected="selectOrganization"
                />
                <x-admin::form.control-group.error control-name="organization_id" />
                
                <!-- Selected Organization Info -->
                <div v-if="selectedOrganization" class="mt-2 p-2 bg-green-100 border border-green-300 rounded text-sm text-green-800">
                    <i class="icon-check-circle"></i> <span>{{ selectedOrganization.name }}</span>
                </div>
            </div>

            <!-- Add New Organization Button -->
            <div class="mb-4">
                <button
                    type="button"
                    id="add-organization-btn"
                    class="flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    @click="showOrganizationForm = !showOrganizationForm"
                >
                    <i class="icon-plus text-xs mr-1"></i>Nieuwe organisatie toevoegen
                </button>
            </div>

            <!-- New Organization Form -->
            <div v-if="showOrganizationForm" id="new-organization-form" class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
                <div class="grid grid-cols-1 gap-4">
                    <!-- Organization Name -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.contacts.organizations.create.name')
                        </x-admin::form.control-group.label>
                        
                        <x-admin::form.control-group.control
                            type="text"
                            name="new_organization_name"
                            id="new_organization_name"
                            rules="required"
                            :placeholder="trans('admin::app.contacts.organizations.create.name')"
                        />
                        <x-admin::form.control-group.error control-name="new_organization_name" />
                    </x-admin::form.control-group>

                    <!-- Address Component -->
                    <div class="mt-2">
                        @include('admin::components.address', [
                            'id' => 'new_org_address',
                            'namePrefix' => 'new_organization_address',
                            'entity' => null,
                            'hideTitle' => true
                        ])
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            @click="cancelOrganizationForm"
                        >
                            Annuleren
                        </button>
                        
                        <button
                            type="button"
                            id="save-organization-btn"
                            class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            @click="saveNewOrganization"
                        >
                            Organisatie opslaan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden field for selected organization ID -->
<input type="hidden" name="organization_id" :value="selectedOrganization?.id || ''" />

{!! view_render_event('admin.leads.organization.after') !!}

@pushOnce('scripts')
<script>
const ORGANIZATION_STORE_URL = '{{ route("admin.contacts.organizations.store") }}';

Vue.createApp({
    data() {
        return {
            selectedOrganization: @json($organization ?? null),
            showOrganizationForm: false
        }
    },
    
    methods: {
        selectOrganization(org) {
            this.selectedOrganization = org;
            console.log('Organization selected:', org);
        },
        
        cancelOrganizationForm() {
            this.showOrganizationForm = false;
            this.clearOrganizationForm();
        },
        
        clearOrganizationForm() {
            const nameField = document.getElementById('new_organization_name');
            const postcodeField = document.getElementById('new_org_address_postal_code');
            const houseNumberField = document.getElementById('new_org_address_house_number');
            const streetField = document.getElementById('new_org_address_street');
            const suffixField = document.getElementById('new_org_address_house_number_suffix');
            const cityField = document.getElementById('new_org_address_city');
            const stateField = document.getElementById('new_org_address_state');
            const countryField = document.getElementById('new_org_address_country');

            if (nameField) nameField.value = '';
            if (postcodeField) postcodeField.value = '';
            if (houseNumberField) houseNumberField.value = '';
            if (streetField) streetField.value = '';
            if (suffixField) suffixField.value = '';
            if (cityField) cityField.value = '';
            if (stateField) stateField.value = '';
            if (countryField) countryField.value = 'Nederland';
        },
        
        async saveNewOrganization() {
            const nameField = document.getElementById('new_organization_name');
            const postalCodeField = document.getElementById('new_org_address_postal_code');
            const houseNumberField = document.getElementById('new_org_address_house_number');
            const streetField = document.getElementById('new_org_address_street');
            const cityField = document.getElementById('new_org_address_city');
            const stateField = document.getElementById('new_org_address_state');
            const countryField = document.getElementById('new_org_address_country');

            if (!nameField || !nameField.value.trim()) {
                alert('Vul een organisatienaam in.');
                return;
            }

            if (!postalCodeField || !postalCodeField.value.trim()) {
                alert('Vul een postcode in.');
                return;
            }

            if (!houseNumberField || !houseNumberField.value.trim()) {
                alert('Vul een huisnummer in.');
                return;
            }

            const saveBtn = document.getElementById('save-organization-btn');
            const originalText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Opslaan...';

            try {
                const formData = new FormData();
                formData.append('name', nameField.value.trim());
                formData.append('address[postal_code]', postalCodeField.value.trim());
                formData.append('address[house_number]', houseNumberField.value.trim());
                formData.append('address[street]', streetField ? streetField.value.trim() : '');
                formData.append('address[house_number_suffix]', document.getElementById('new_org_address_house_number_suffix') ? document.getElementById('new_org_address_house_number_suffix').value.trim() : '');
                formData.append('address[city]', cityField ? cityField.value.trim() : '');
                formData.append('address[state]', stateField ? stateField.value.trim() : '');
                formData.append('address[country]', countryField ? countryField.value.trim() : 'Nederland');

                // Get CSRF token safely
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                 document.querySelector('input[name="_token"]')?.value ||
                                 '';
                
                if (csrfToken) {
                    formData.append('_token', csrfToken);
                }

                const response = await fetch(ORGANIZATION_STORE_URL, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const result = await response.json();

                if (response.ok && result.data) {
                    // Select the new organization
                    this.selectedOrganization = {
                        id: result.data.id,
                        name: result.data.name
                    };

                    // Show success message
                    if (window.$emitter) {
                        window.$emitter.emit('add-flash', {
                            type: 'success',
                            message: 'Organisatie succesvol aangemaakt en geselecteerd!'
                        });
                    }

                    // Hide the form and clear it
                    this.showOrganizationForm = false;
                    this.clearOrganizationForm();
                } else {
                    throw new Error(result.message || 'Er is een fout opgetreden bij het aanmaken van de organisatie.');
                }
            } catch (error) {
                alert('Fout bij opslaan: ' + error.message);
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
        }
    },
    
    mounted() {
        console.log('Organization Vue component mounted');
        console.log('Selected organization:', this.selectedOrganization);
    }
}).mount('#organization');
</script>
@endPushOnce