@include('adminc.components.entity-selector')
@include('adminc.components.sales-lead-suggestion')
@include('adminc.components.sales-lead-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-sales-lead-selector-template">
        <v-entity-selector
            :name="name || 'sales_lead_id'"
            :label="label || 'Sales Lead'"
            :placeholder="placeholder || 'Selecteer sales lead...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="false"
            :fetcher="fetchSalesLeads"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <v-sales-lead-suggestion :sales-lead="item" />
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-sales-lead-selector', {
            template: '#v-sales-lead-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute'],
            emits: ['select','remove','create-new','change','update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/sales-leads/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{ id: this.currentValue, name: this.currentLabel || '' }];
                    }
                    return [];
                }
            },
            methods: {
                async fetchSalesLeads(query) {
                    if (window.adminc && typeof window.adminc.fetchSalesLeads === 'function') {
                        return await window.adminc.fetchSalesLeads(query);
                    } else {
                        console.error('Could not find global fetchSalesLeads function');
                    }
                    return [];
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

