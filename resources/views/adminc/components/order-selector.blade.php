@include('adminc.components.entity-selector')
@include('adminc.components.order-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-order-selector-template">
        <v-entity-selector
            :name="name || 'order_id'"
            :label="label || 'Order'"
            :placeholder="placeholder || 'Zoek order (nummer of titel)...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="false"
            :fetcher="fetchOrders"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <div class="flex flex-col gap-0.5">
                    <div class="font-medium">{{ item.name || item.title || ('#' + item.id) }}</div>
                    <div v-if="item.subtitle || item.stage?.name" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.subtitle || item.stage?.name }}
                    </div>
                </div>
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-order-selector', {
            template: '#v-order-selector-template',
            props: ['name', 'label', 'placeholder', 'currentValue', 'currentLabel', 'canAddNew', 'searchRoute'],
            emits: ['change', 'update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/orders/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{
                            id: this.currentValue,
                            name: this.currentLabel || ('Order #' + this.currentValue),
                        }];
                    }

                    return [];
                },
            },
            methods: {
                async fetchOrders(query) {
                    if (window.adminc && typeof window.adminc.fetchOrders === 'function') {
                        return await window.adminc.fetchOrders(query, {
                            baseUrl: this.resolvedSearchRoute,
                        });
                    }
                    console.error('Could not find global fetchOrders function');

                    return [];
                },
                onItemsUpdated(items) {
                    const first = Array.isArray(items) && items.length ? items[0] : null;
                    this.$emit('change', first);
                    this.$emit('update:value', first ? first.id : null);
                },
            },
        });
    </script>
@endverbatim
@endPushOnce
