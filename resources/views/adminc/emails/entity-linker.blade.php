@include('adminc.components.entity-selector')
@include('adminc.components.contact-person-selector')
@include('adminc.components.lead-selector')
@include('adminc.components.sales-lead-selector')
@include('adminc.components.clinic-selector')

@pushOnce('scripts')
    @verbatim
        <script type="text/x-template" id="v-entity-linker-template">
            <div class="flex flex-col gap-4">
                <!-- Entity Type Selector -->
                <div class="flex gap-2">
                    <button
                        v-for="type in entityTypes"
                        :key="type.value"
                        @click="selectedEntityType = type.value"
                        :class="[
                            'flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                            selectedEntityType === type.value
                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                        ]"
                    >
                        {{ type.label }}
                    </button>
                </div>

                <!-- Link Fields based on selected type -->
                <template v-if="selectedEntityType === 'lead'">
                    <v-lead-selector
                        label="Lead"
                        placeholder="Zoek lead..."
                        :search-route="leadSearchRoute"
                        :multiple="false"
                        :items="leadItems"
                        @update:items="onLeadSelected"
                    />
                </template>

                <template v-else-if="selectedEntityType === 'sales_lead'">
                    <v-sales-lead-selector
                        label="Sales Lead"
                        placeholder="Zoek sales lead..."
                        :search-route="salesLeadSearchRoute"
                        :current-value="email?.sales_lead_id || null"
                        :current-label="email?.sales_lead?.name || ''"
                        :can-add-new="false"
                        @change="onSalesLeadSelected"
                        @update:value="val => { /* handled by @change */ }"
                    />
                </template>

                <template v-else-if="selectedEntityType === 'person'">
                    <v-contact-person-selector
                        label="Contact"
                        placeholder="Zoek contact..."
                        :current-value="email?.person_id || null"
                        :current-label="email?.person?.name || ''"
                        :can-add-new="true"
                        @change="onPersonSelected"
                        @update:value="val => { /* handled by @change */ }"
                    />
                </template>

                <template v-else-if="selectedEntityType === 'clinic'">
                    <v-clinic-selector
                        label="Clinic"
                        placeholder="Zoek clinic..."
                        :current-value="email?.clinic_id || null"
                        :current-label="email?.clinic?.name || ''"
                        :can-add-new="false"
                        @change="onClinicSelected"
                        @update:value="val => { /* handled by @change */ }"
                    />
                </template>
            </div>
        </script>

        <!-- Entity Linker Component -->
        <script type="module">
            app.component('v-entity-linker', {
                template: '#v-entity-linker-template',
                props: {
                    email: Object,
                    unlinking: Object,
                    leadSearchRoute: String,
                    salesLeadSearchRoute: String,
                },
                emits: ['link-entity', 'unlink-entity'],
                data() {
                    return {
                        selectedEntityType: 'lead',
                        pendingEntity: null,
                        leadItems: [],
                        salesLeadItems: [],
                        entityTypes: [
                            { value: 'lead', label: 'Lead' },
                            { value: 'sales_lead', label: 'Sales' },
                            { value: 'person', label: 'Contact' },
                            { value: 'clinic', label: 'Clinic' },
                        ],
                    };
                },
                mounted() {
                    // Set initial entity type based on existing relationships
                    if (this.email?.lead_id) {
                        this.selectedEntityType = 'lead';
                        this.leadItems = [{ id: this.email.lead_id, name: this.email.lead?.name || `Lead #${this.email.lead_id}` }];
                    } else if (this.email?.sales_lead_id) {
                        this.selectedEntityType = 'sales_lead';
                        this.salesLeadItems = [{ id: this.email.sales_lead_id, name: this.email.sales_lead?.name || `Sales #${this.email.sales_lead_id}` }];
                    } else if (this.email?.person_id) {
                        this.selectedEntityType = 'person';
                    } else if (this.email?.clinic_id) {
                        this.selectedEntityType = 'clinic';
                    }
                },
                methods: {
                    onLeadSelected(items) {
                        const item = Array.isArray(items) && items.length > 0 ? items[0] : null;
                        if (item) {
                            this.pendingEntity = { ...item, type: 'lead' };
                            this.saveSelection();
                        }
                    },
                    onSalesLeadSelected(salesLead) {
                        if (salesLead) {
                            this.pendingEntity = { ...salesLead, type: 'sales_lead' };
                            this.saveSelection();
                        }
                    },
                    onPersonSelected(person) {
                        if (person) {
                            this.pendingEntity = { ...person, type: 'person' };
                            this.saveSelection();
                        }
                    },
                    onClinicSelected(clinic) {
                        if (clinic) {
                            this.pendingEntity = { ...clinic, type: 'clinic' };
                            this.saveSelection();
                        }
                    },
                    saveSelection() {
                        if (!this.pendingEntity) return;
                        const ent = this.pendingEntity;
                        // Ensure a 'type' is present
                        if (!ent.type && this.selectedEntityType) {
                            ent.type = this.selectedEntityType;
                        }
                        this.$emit('link-entity', ent);
                        this.pendingEntity = null;
                    },
                    handleUnlink() {
                        this.$emit('unlink-entity', this.selectedEntityType);
                    },
                },
            });
        </script>
    @endverbatim
@endPushOnce

