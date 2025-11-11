@include('adminc.components.entity-selector')
@include('adminc.components.lead-suggestion')
@include('adminc.components.lead-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-lead-selector-template">
        <v-entity-selector
            :name="name || 'lead_id'"
            :label="label || 'Lead'"
            :placeholder="placeholder || 'Selecteer lead...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="false"
            :fetcher="fetchLeads"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <v-lead-suggestion :lead="item" />
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-lead-selector', {
            template: '#v-lead-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute'],
            emits: ['select','remove','create-new','change','update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/leads/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{ id: this.currentValue, name: this.currentLabel || '' }];
                    }
                    return [];
                }
            },
            methods: {
                async fetchLeads(query) {
                    if (window.adminc && typeof window.adminc.fetchLeads === 'function') {
                        return await window.adminc.fetchLeads(query);
                    } else {
                        console.error('Could not find global fetchLeads function');
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

