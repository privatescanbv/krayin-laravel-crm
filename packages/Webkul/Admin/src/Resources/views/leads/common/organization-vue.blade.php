{!! view_render_event('admin.leads.organization.before') !!}

@include('adminc.components.entity-selector')

<!-- Lead Organization Section -->
<v-organization></v-organization>

{!! view_render_event('admin.leads.organization.after') !!}

@pushOnce('scripts')
<script type="text/x-template" id="v-organization-template">
    <div class="flex flex-col gap-4">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-4">

                {{-- Organisation lookup: hidden while create form is open or a new org is confirmed --}}
                <div v-if="!showOrgForm && !orgConfirmed" class="mb-4">
                    <v-entity-selector
                        name="organization_id"
                        label="Organisatie"
                        hint="Koppel een organisatie voor facturatie doeleinden (optioneel)"
                        placeholder="Zoek organisatie..."
                        search-route="{{ route('admin.contacts.organizations.search') }}"
                        :items="selectedOrganization ? [selectedOrganization] : []"
                        :can-add-new="true"
                        @create-new="onCreateNew"
                        @select="onOrgSelected"
                        @remove="selectedOrganization = null"
                    />
                    <x-admin::form.control-group.error control-name="organization_id" />
                </div>

                {{-- New org confirmed summary --}}
                <div v-if="!showOrgForm && orgConfirmed"
                     class="flex items-center justify-between p-3 border rounded bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700 mb-4">
                    <span class="text-sm text-green-800 dark:text-green-300">
                        Nieuwe organisatie <strong>@{{ newOrgName }}</strong> wordt aangemaakt bij opslaan.
                    </span>
                    <div class="flex gap-2 ml-3 shrink-0">
                        <button type="button" @click="editOrgForm"
                                class="text-xs text-blue-600 hover:underline dark:text-blue-400">Wijzigen</button>
                        <button type="button" @click="cancelOrgForm"
                                class="text-xs text-red-600 hover:underline dark:text-red-400">Annuleren</button>
                    </div>
                </div>

                {{-- Hidden inputs: new org fields submitted with main form --}}
                <template v-if="orgConfirmed">
                    <input type="hidden" name="new_organization_name" :value="newOrgName.trim()">
                    <input type="hidden" name="new_organization_address[postal_code]" :value="newOrgPostal.trim()">
                    <input type="hidden" name="new_organization_address[house_number]" :value="newOrgHouseNumber.trim()">
                    <input type="hidden" name="new_organization_address[house_number_suffix]" :value="newOrgSuffix.trim()">
                    <input type="hidden" name="new_organization_address[street]" :value="newOrgStreet.trim()">
                    <input type="hidden" name="new_organization_address[city]" :value="newOrgCity.trim()">
                    <input type="hidden" name="new_organization_address[country]" :value="newOrgCountry || 'Nederland'">
                </template>

                {{-- Inline new organisation form (shared partial) --}}
                @include('adminc.organizations.inline-create-form')

            </div>
        </div>
    </div>
</script>

<script type="module">
app.component('v-organization', {
    template: '#v-organization-template',
    data() {
        return {
            selectedOrganization: @json(($selectedOrganization ?? null) ? ['id' => ($selectedOrganization['id'] ?? $selectedOrganization->id ?? null), 'name' => ($selectedOrganization['name'] ?? $selectedOrganization->name ?? '')] : null),
            showOrgForm: false,
            orgConfirmed: false,
            newOrgName: '',
            newOrgPostal: '',
            newOrgHouseNumber: '',
            newOrgSuffix: '',
            newOrgStreet: '',
            newOrgCity: '',
            newOrgCountry: 'Nederland',
            isLookingUpAddress: false,
        };
    },

    methods: {
        onOrgSelected(org) {
            this.selectedOrganization = org;
            this.cancelOrgForm();
        },

        onCreateNew({ query }) {
            this.selectedOrganization = null;
            this.orgConfirmed = false;
            this.newOrgName = query || '';
            this.showOrgForm = true;
        },

        cancelOrgForm() {
            this.showOrgForm = false;
            this.orgConfirmed = false;
            this.newOrgName = '';
            this.newOrgPostal = '';
            this.newOrgHouseNumber = '';
            this.newOrgSuffix = '';
            this.newOrgStreet = '';
            this.newOrgCity = '';
            this.newOrgCountry = 'Nederland';
        },

        editOrgForm() {
            this.orgConfirmed = false;
            this.showOrgForm = true;
        },

        confirmOrgForm() {
            if (!this.newOrgName.trim()) {
                alert('Vul een organisatienaam in.');
                return;
            }
            if (!this.newOrgHouseNumber.trim()) {
                alert('Vul een huisnummer in.');
                return;
            }
            this.orgConfirmed = true;
            this.showOrgForm = false;
        },

        async lookupAddress() {
            if (!this.newOrgPostal.trim() || !this.newOrgHouseNumber.trim()) {
                alert('Vul eerst postcode en huisnummer in.');
                return;
            }
            this.isLookingUpAddress = true;
            try {
                const url = '/admin/address/lookup?postcode=' + encodeURIComponent(this.newOrgPostal.trim())
                    + '&huisnummer=' + encodeURIComponent(this.newOrgHouseNumber.trim());
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (data.success) {
                    this.newOrgStreet  = data.street  || '';
                    this.newOrgCity    = data.city    || '';
                    this.newOrgCountry = data.country || 'Nederland';
                } else {
                    alert(data.message || 'Adres niet gevonden.');
                }
            } catch (e) {
                alert('Fout bij opzoeken adres: ' + e.message);
            } finally {
                this.isLookingUpAddress = false;
            }
        },
    },
});
</script>
@endPushOnce
