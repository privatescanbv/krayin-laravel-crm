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
            :multiple="multiple !== false"
            :fetcher="resolvedFetcher"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <div class="font-medium truncate" :title="item.name_with_path || item.name || ''">
                    {{ item.name }}
                </div>
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-product-selector', {
            template: '#v-product-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute','multiple'],
            emits: ['select','remove','create-new','change','update:value'],
            data() {
                return {
                    loadedProductName: null,
                };
            },
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/products/search';
                },
                resolvedFetcher() {
                    // If searchRoute is set to partner_products.search, use searchRoute directly (no custom fetcher)
                    // Otherwise use fetchProducts for regular product search
                    if (this.searchRoute && this.searchRoute.includes('partner_products')) {
                        return null; // Let v-entity-selector use searchRoute directly
                    }
                    return this.fetchProducts;
                },
                computedItems() {
                    if (this.currentValue) {
                        const displayName = this.currentLabel || this.loadedProductName || '';
                        return [{ id: this.currentValue, name: displayName, name_with_path: displayName }];
                    }
                    return [];
                }
            },
            watch: {
                currentValue: {
                    immediate: true,
                    handler(newVal) {
                        // If we have a currentValue but no currentLabel, fetch the product name
                        if (newVal && !this.currentLabel && !this.loadedProductName) {
                            this.loadProductName(newVal);
                        }
                    }
                }
            },
            mounted() {
                // Also check on mount in case currentValue was set before component mounted
                if (this.currentValue && !this.currentLabel && !this.loadedProductName) {
                    this.loadProductName(this.currentValue);
                }
            },
            methods: {
                async loadProductName(productId) {
                    try {
                        // Use dedicated endpoint to get product name by ID
                        const response = await axios.get(`/admin/products/${productId}/name`);
                        if (response && response.data) {
                            this.loadedProductName = response.data.name_with_path || response.data.name || '';
                        }
                    } catch (e) {
                        console.warn('Failed to load product name for ID:', productId, e);
                    }
                },
                async fetchProducts(query) {
                    if (window.adminc && typeof window.adminc.fetchProducts === 'function') {
                        const products = await window.adminc.fetchProducts(query);
                        // Ensure name_with_path is used for display, fallback to name
                        return products.map(p => ({
                            ...p,
                            name: p.name || '',
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
                    document.dispatchEvent(new CustomEvent('adminc:product-selected', {
                        detail: { id: first ? first.id : null, fieldName: this.name || 'product_id' }
                    }));
                }
            }
        });
    </script>
@endverbatim
@endPushOnce

