@verbatim
<script type="text/x-template" id="v-quote-item-list-template">
    <div class="box-shadow rounded-lg border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-800">
            <div class="text-base font-semibold text-gray-800 dark:text-white">
                @lang('admin::app.quotes.create.quote-items')
            </div>

            <button type="button" class="secondary-button" @click="addEmptyRow">
                @lang('admin::app.common.add')
            </button>
        </div>

        <div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
            <template v-if="rows.length">
                <div v-for="(row, index) in rows" :key="row.uid" class="flex items-center gap-2 px-4 py-2">
                    <x-admin::form.control-group class="w-6/12">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.quotes.create.product')
                        </x-admin::form.control-group.label>

                        <v-product-lookup
                            :value="row.product"
                            @selected="setProduct(index, $event)"
                        />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="w-2/12">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.quotes.create.qty')
                        </x-admin::form.control-group.label>

                        <input type="number" min="1" class="form-input" v-model.number="row.quantity" @change="recalculate(index)" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="w-2/12">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.quotes.create.price')
                        </x-admin::form.control-group.label>

                        <input type="number" step="0.01" min="0" class="form-input" v-model.number="row.price" @change="recalculate(index)" />
                    </x-admin::form.control-group>

                    <div class="w-2/12 text-right font-medium">
                        @{{ formatPrice(row.total) }}
                    </div>

                    <button type="button" class="icon-button !p-1" @click="removeRow(index)">
                        <span class="icon-delete text-red-600"></span>
                    </button>
                </div>
            </template>

            <div v-else class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                @lang('admin::app.common.no-result-found')
            </div>
        </div>

        <div class="flex items-center justify-end gap-6 border-t border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
            <div class="flex items-center gap-2">
                <span class="text-gray-600 dark:text-gray-300">@lang('admin::app.quotes.create.subtotal')</span>
                <span class="font-semibold">@{{ formatPrice(subtotal) }}</span>
            </div>
        </div>
    </div>
</script>
@endverbatim

<script type="module">
    app.component('v-quote-item-list', {
        template: '#v-quote-item-list-template',
        props: {
            errors: Object,
            // When editing, backend can inject products as JSON via include param
            products: { type: Array, default: () => [] },
        },
        data() {
            return {
                rows: this.initializeRows(),
            };
        },
        computed: {
            subtotal() {
                return this.rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);
            },
        },
        methods: {
            initializeRows() {
                if (Array.isArray(this.products) && this.products.length > 0) {
                    return this.products.map((item) => ({
                        uid: crypto.randomUUID(),
                        product: item.product ?? null,
                        product_id: item.product_id ?? null,
                        quantity: Number(item.quantity ?? 1),
                        price: Number(item.price ?? 0),
                        total: Number((item.quantity ?? 1) * (item.price ?? 0)),
                    }));
                }

                return [];
            },

            addEmptyRow() {
                this.rows.push({
                    uid: crypto.randomUUID(),
                    product: null,
                    product_id: null,
                    quantity: 1,
                    price: 0,
                    total: 0,
                });
            },

            setProduct(index, product) {
                this.rows[index].product = product;
                this.rows[index].product_id = product?.id ?? null;
                this.rows[index].price = Number(product?.price ?? 0);
                this.recalculate(index);
            },

            recalculate(index) {
                const row = this.rows[index];
                row.total = Number(row.quantity || 0) * Number(row.price || 0);
            },

            removeRow(index) {
                this.rows.splice(index, 1);
            },

            formatPrice(value) {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
            },
        },
    });

    // Mock minimal product lookup component to avoid render errors during tests
    app.component('v-product-lookup', {
        props: ['value'],
        emits: ['selected'],
        template: '<input class="form-input" @change="$emit(\'selected\', { id: 1, name: \"Product\", price: 0 })" />'
    });
</script>
@endverbatim

