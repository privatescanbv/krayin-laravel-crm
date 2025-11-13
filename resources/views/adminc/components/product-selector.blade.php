@include('adminc.components.entity-selector')
@include('adminc.components.product-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-product-selector-template">
        <v-entity-selector
            :name="name || 'product_id'"
            :label="label"
            :placeholder="placeholder || 'Selecteer product...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="multiple !== false ? false : false"
            :fetcher="fetchProducts"
            @update:items="onItemsUpdated"
        />
    </script>

    <script type="module">
        app.component('v-product-selector', {
            template: '#v-product-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute','multiple'],
            emits: ['select','remove','create-new','change','update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/products/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{ id: this.currentValue, name: this.currentLabel || '', name_with_path: this.currentLabel || '' }];
                    }
                    return [];
                }
            },
            methods: {
                async fetchProducts(query) {
                    if (window.adminc && typeof window.adminc.fetchProducts === 'function') {
                        const products = await window.adminc.fetchProducts(query);
                        // Ensure name_with_path is used for display, fallback to name
                        return products.map(p => ({
                            ...p,
                            name: p.name_with_path || p.name || '',
                            name_with_path: p.name_with_path || p.name || ''
                        }));
                    } else {
                        console.error('Could not find global fetchProducts function');
                    }
                    return [];
                },
                onItemsUpdated(items) {
                    const first = Array.isArray(items) && items.length ? items[0] : null;
                    this.$emit('change', first);
                    this.$emit('update:value', first ? first.id : null);
                    this.$emit('select', first);
                }
            }
        });
    </script>
@endverbatim
@endPushOnce

