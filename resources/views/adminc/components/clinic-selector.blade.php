@include('adminc.components.entity-selector')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-clinic-selector-template">
        <v-entity-selector
            :name="name || 'clinic_id'"
            :label="label || 'Clinic'"
            :placeholder="placeholder || 'Selecteer clinic...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="false"
            :fetcher="fetchClinics"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex-1">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</div>
                    </div>
                </div>
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-clinic-selector', {
            template: '#v-clinic-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute'],
            emits: ['select','remove','create-new','change','update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/settings/clinics/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{ id: this.currentValue, name: this.currentLabel || '' }];
                    }
                    return [];
                }
            },
            methods: {
                async fetchClinics(query) {
                    try {
                        const response = await axios.get(this.resolvedSearchRoute, {
                            params: { query: query }
                        });
                        const data = (response && response.data && (response.data.data || response.data)) || [];
                        return Array.isArray(data) ? data : [];
                    } catch (error) {
                        console.error('Clinic search failed', error);
                        return [];
                    }
                },
                onItemsUpdated(items) {
                    const first = Array.isArray(items) && items.length ? items[0] : null;
                    this.$emit('change', first);
                    this.$emit('update:value', first ? first.id : null);
                }
            }
        });
    </script>
@endverbatim
@endPushOnce

