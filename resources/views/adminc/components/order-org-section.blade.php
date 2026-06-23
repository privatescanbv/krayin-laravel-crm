@include('adminc.components.entity-selector')

@pushOnce('scripts', 'adminc:components:order-org-section')

<script type="text/x-template" id="v-order-org-section-template">
    <div class="w-full flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Zakelijk</span>
            <label class="inline-flex cursor-pointer items-center gap-3">
                <input type="checkbox"
                       class="sr-only"
                       :checked="isBusiness === '1'"
                       @change="onBusinessToggle">
                <span class="relative h-6 w-11 shrink-0 rounded-full transition-colors focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900"
                      :class="isBusiness === '1' ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'"
                      aria-hidden="true">
                    <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                          :class="isBusiness === '1' ? 'translate-x-5' : 'translate-x-0'"></span>
                </span>
                <span class="text-sm text-gray-600 dark:text-gray-300" v-text="isBusiness === '1' ? 'Ja' : 'Nee'"></span>
            </label>
            <input type="hidden" name="is_business" :value="isBusiness">
        </div>
        <div v-if="isBusiness == '1'" class="flex flex-col gap-1">
            {{-- Organisation lookup: hidden while create form is open or a new org is confirmed --}}
            <v-entity-selector
                v-if="!showOrgForm && !orgConfirmed"
                name="organization_id"
                label="Organisatie"
                placeholder="Zoek organisatie..."
                :search-route="searchRoute"
                :items="selectedOrg ? [selectedOrg] : []"
                :can-add-new="true"
                @select="onOrgSelected"
                @remove="selectedOrg = null"
                @create-new="onCreateNew"
            ></v-entity-selector>

            {{-- New org confirmed summary --}}
            <div v-if="!showOrgForm && orgConfirmed" class="flex items-center justify-between p-3 border rounded bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700">
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
                <input type="hidden" name="new_org[name]" :value="newOrgName.trim()">
                <input type="hidden" name="new_org[postal_code]" :value="newOrgPostal.trim()">
                <input type="hidden" name="new_org[house_number]" :value="newOrgHouseNumber.trim()">
                <input type="hidden" name="new_org[house_number_suffix]" :value="newOrgSuffix.trim()">
                <input type="hidden" name="new_org[street]" :value="newOrgStreet.trim()">
                <input type="hidden" name="new_org[city]" :value="newOrgCity.trim()">
                <input type="hidden" name="new_org[country]" :value="newOrgCountry || 'Nederland'">
            </template>

            <p v-if="isBusiness == '1' && !selectedOrg && !orgConfirmed && !showOrgForm" class="text-xs text-red-500 mt-1">Organisatie is verplicht bij zakelijke order.</p>

            {{-- Inline new organisation form (shared partial) --}}
            @include('adminc.organizations.inline-create-form')
        </div>
    </div>
</script>

<script type="module">
    (function () {
        const app = window.app;

        if (!app) {
            return;
        }
        app.component('v-order-org-section', {
            template: '#v-order-org-section-template',
            props: {
                initialIsBusiness: {
                    type: Boolean,
                    default: false,
                },
                initialOrg: {
                    default: null,
                },
                hintOrg: {
                    default: null,
                },
                searchRoute: {
                    type: String,
                    default: '',
                },
            },
            mounted() {
                if (this.initialIsBusiness && ! this.selectedOrg && this.hintOrg) {
                    const h = this.normalizeOrgProp(this.hintOrg);
                    if (h) {
                        this.selectedOrg = h;
                    }
                }
            },
            data() {
                return {
                    isBusiness: this.initialIsBusiness ? '1' : '0',
                    selectedOrg: this.normalizeOrgProp(this.initialOrg),
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
                onBusinessToggle(event) {
                    const on = event.target.checked;
                    this.isBusiness = on ? '1' : '0';
                    if (! on) {
                        this.selectedOrg = null;
                        this.cancelOrgForm();
                    } else if (! this.selectedOrg && this.hintOrg) {
                        const h = this.normalizeOrgProp(this.hintOrg);
                        if (h) {
                            this.selectedOrg = h;
                        }
                    }
                },
                normalizeOrgProp(value) {
                    if (value == null || typeof value !== 'object') {
                        return null;
                    }
                    const id = value.id ?? value.value ?? null;
                    if (id == null) {
                        return null;
                    }
                    return {
                        id,
                        name: value.name ?? value.label ?? value.text ?? String(id),
                    };
                },
                onOrgSelected(org) {
                    this.selectedOrg = org;
                    this.cancelOrgForm();
                },
                onCreateNew({ query }) {
                    this.selectedOrg = null;
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
    })();
</script>

@endPushOnce
