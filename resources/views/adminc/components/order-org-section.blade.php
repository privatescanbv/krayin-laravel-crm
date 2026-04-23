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
                    :can-add-new="false"
                    @select="selectedOrg = $event"
                    @remove="selectedOrg = null"
                ></v-entity-selector>
                <p v-if="isBusiness == '1' && !selectedOrg" class="text-xs text-red-500 mt-1">Organisatie is verplicht bij zakelijke order.</p>
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
                    };
                },
                methods: {
                    onBusinessToggle(event) {
                        const on = event.target.checked;
                        this.isBusiness = on ? '1' : '0';
                        if (! on) {
                            this.selectedOrg = null;
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
                },
            });
        })();
    </script>
@endverbatim
@endPushOnce
