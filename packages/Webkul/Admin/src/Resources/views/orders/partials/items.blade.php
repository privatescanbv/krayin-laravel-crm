<div id="order-items" class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold text-gray-800 dark:text-white">Orderregels</p>
        <p class="text-sm text-gray-600 dark:text-white">Voeg producten toe met aantallen en bedragen.</p>
    </div>

    <v-order-item-list :errors="errors" :data='@json((isset($order) ? $order->orderRegels : []))'></v-order-item-list>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-order-item-list-template">
        <div class="flex flex-col gap-4">
            <x-admin::table>
                <x-admin::table.thead>
                    <x-admin::table.thead.tr>
                        <x-admin::table.th>Product</x-admin::table.th>
                        <x-admin::table.th class="text-center">Aantal</x-admin::table.th>
                        <x-admin::table.th class="text-center">Totaal</x-admin::table.th>
                        <x-admin::table.th class="text-center"></x-admin::table.th>
                    </x-admin::table.thead.tr>
                </x-admin::table.thead>
                <x-admin::table.tbody>
                    <template v-for="(item, index) in items" :key="index">
                        <v-order-item :item="item" :index="index" :errors="errors" @onRemoveItem="removeItem($event)"></v-order-item>
                    </template>
                </x-admin::table.tbody>
            </x-admin::table>

            <span class="text-md flex max-w-max cursor-pointer items-center gap-2 text-brandColor" @click="addItem">Regel toevoegen</span>
        </div>
    </script>

    <script type="text/x-template" id="v-order-item-template">
        <x-admin::table.thead.tr>
            <x-admin::table.td>
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::product-lookup
                        ::src="src"
                        ::name="`${inputName}[product_id]`"
                        placeholder="Zoek product"
                        ::key="(item.id ? item.id : ('new-' + index)) + '-' + (item.product_id ? item.product_id : '')"
                        ::value="displayValue"
                        @on-selected="(product) => selectProduct(product)"
                    />
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.control type="inline" ::name="`${inputName}[quantity]`" ::value="item.quantity" rules="required|integer|min:1" ::errors="errors" label="Aantal" placeholder="Aantal" @on-change="(e) => item.quantity = e.value" position="center" />
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.control type="inline" ::name="`${inputName}[total_price]`" ::value="item.total_price" rules="required|decimal:2" ::errors="errors" label="Totaal" placeholder="Totaal" @on-change="(e) => item.total_price = e.value" position="center" />
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <x-admin::form.control-group class="!mb-0">
                    <i @click="removeItem" class="icon-delete cursor-pointer text-2xl"></i>
                </x-admin::form.control-group>
            </x-admin::table.td>
        </x-admin::table.thead.tr>
    </script>

    <script type="module">
        app.component('v-order-item-list', {
            template: '#v-order-item-list-template',
            props: ['errors', 'data'],
            data() {
                return {
                    items: this.data && this.data.length ? this.data.map(r => ({
                        id: r.id ?? null,
                        product_id: r.product_id ?? null,
                        quantity: r.quantity ?? 1,
                        total_price: r.total_price ?? 0,
                    })) : [{ id: null, product_id: null, quantity: 1, total_price: 0 }],
                };
            },
            methods: {
                addItem() {
                    this.items.push({ id: null, product_id: null, quantity: 1, total_price: 0 });
                },
                removeItem(item) {
                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            if (this.items.length === 1) {
                                this.items = [{ id: null, product_id: null, quantity: 1, total_price: 0 }];
                            } else {
                                const index = this.items.indexOf(item);
                                if (index !== -1) this.items.splice(index, 1);
                            }
                        },
                    });
                },
            },
        });

        app.component('v-order-item', {
            template: '#v-order-item-template',
            props: ['index', 'item', 'errors'],
            computed: {
                inputName() {
                    return this.item.id ? `items[${this.item.id}]` : `items[item_${this.index}]`;
                },
                src() {
                    return "{{ route('admin.products.search') }}";
                },
                displayValue() {
                    const id = this.item.product_id || this.item.id || null;
                    const name = this.item.product_name || this.item.name || '';
                    return id ? { id, name } : {};
                },
            },
            methods: {
                selectProduct(result) {
                    try { console.log('[v-order-item] selectProduct', result); } catch (e) {}
                    this.item.product_id = result.id;
                    this.item.product_name = result.name;
                },
                removeItem() {
                    try { console.log('[v-order-item] removeItem'); } catch (e) {}
                    this.$emit('onRemoveItem', this.item);
                },
            },
        });
    </script>
@endPushOnce
