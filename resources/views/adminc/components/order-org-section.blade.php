@include('adminc.components.entity-selector')

@pushOnce('scripts', 'adminc:components:order-org-section')
@verbatim
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
                <v-entity-selector
                    name="organization_id"
                    label="Organisatie"
                    placeholder="Zoek organisatie..."
                    :search-route="searchRoute"
                    :items="selectedOrg ? [selectedOrg] : []"
                    :can-add-new="true"
                    @select="selectedOrg = $event"
                    @remove="selectedOrg = null"
                    @create-new="onCreateNew"
                ></v-entity-selector>
                <p v-if="isBusiness == '1' && !selectedOrg && !showOrgForm" class="text-xs text-red-500 mt-1">Organisatie is verplicht bij zakelijke order.</p>

                <!-- Inline new organisation form -->
                <div v-if="showOrgForm" class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Nieuwe organisatie aanmaken</p>
                    <div class="grid grid-cols-1 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Naam <span class="text-red-500">*</span></label>
                            <input type="text" v-model="newOrgName" placeholder="Organisatienaam"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Postcode <span class="text-red-500">*</span></label>
                                <input type="text" v-model="newOrgPostal" placeholder="1234 AB"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Huisnummer <span class="text-red-500">*</span></label>
                                <input type="text" v-model="newOrgHouseNumber" placeholder="123"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Toevoeging</label>
                                <input type="text" v-model="newOrgSuffix" placeholder="A"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Straat</label>
                                <input type="text" v-model="newOrgStreet" placeholder="Straatnaam"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stad</label>
                                <input type="text" v-model="newOrgCity" placeholder="Amsterdam"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Land</label>
                                <input type="text" v-model="newOrgCountry" placeholder="Nederland"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cancelOrgForm"
                                class="px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Annuleren
                        </button>
                        <button type="button" @click="saveOrgForm" :disabled="isSavingOrg"
                                class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-60 disabled:cursor-not-allowed">
                            <span v-if="isSavingOrg">Opslaan...</span>
                            <span v-else>Organisatie opslaan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </script>
@endverbatim

<script>
window.__orderOrgSectionStoreUrl = '{{ route("admin.contacts.organizations.store") }}';
</script>

@verbatim
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
                        newOrgName: '',
                        newOrgPostal: '',
                        newOrgHouseNumber: '',
                        newOrgSuffix: '',
                        newOrgStreet: '',
                        newOrgCity: '',
                        newOrgCountry: 'Nederland',
                        isSavingOrg: false,
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
                    onCreateNew({ query }) {
                        this.newOrgName = query || '';
                        this.showOrgForm = true;
                    },
                    cancelOrgForm() {
                        this.showOrgForm = false;
                        this.newOrgName = '';
                        this.newOrgPostal = '';
                        this.newOrgHouseNumber = '';
                        this.newOrgSuffix = '';
                        this.newOrgStreet = '';
                        this.newOrgCity = '';
                        this.newOrgCountry = 'Nederland';
                        this.isSavingOrg = false;
                    },
                    async saveOrgForm() {
                        if (!this.newOrgName.trim()) {
                            alert('Vul een organisatienaam in.');
                            return;
                        }
                        if (!this.newOrgPostal.trim()) {
                            alert('Vul een postcode in.');
                            return;
                        }
                        if (!this.newOrgHouseNumber.trim()) {
                            alert('Vul een huisnummer in.');
                            return;
                        }

                        this.isSavingOrg = true;
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            const formData = new FormData();
                            formData.append('name', this.newOrgName.trim());
                            formData.append('address[postal_code]', this.newOrgPostal.trim());
                            formData.append('address[house_number]', this.newOrgHouseNumber.trim());
                            formData.append('address[house_number_suffix]', this.newOrgSuffix.trim());
                            formData.append('address[street]', this.newOrgStreet.trim());
                            formData.append('address[city]', this.newOrgCity.trim());
                            formData.append('address[country]', this.newOrgCountry.trim() || 'Nederland');
                            if (csrfToken) formData.append('_token', csrfToken);

                            const response = await fetch(window.__orderOrgSectionStoreUrl, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                            });

                            const result = await response.json();

                            if (response.ok && result.data) {
                                this.selectedOrg = { id: result.data.id, name: result.data.name };
                                this.cancelOrgForm();

                                const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                                if (emitter) {
                                    emitter.emit('add-flash', { type: 'success', message: 'Organisatie aangemaakt en geselecteerd!' });
                                }
                            } else {
                                alert('Fout: ' + (result.message || 'Onbekende fout bij aanmaken organisatie.'));
                            }
                        } catch (err) {
                            alert('Fout bij opslaan: ' + err.message);
                        } finally {
                            this.isSavingOrg = false;
                        }
                    },
                },
            });
        })();
    </script>
@endverbatim
@endPushOnce
