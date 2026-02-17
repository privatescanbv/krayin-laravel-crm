@props([
    'order' => null
])
<div id="order-items" class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold text-gray-800 dark:text-white">Orderitems</p>
        <p class="text-sm text-gray-600 dark:text-white">Voeg producten toe met aantallen en bedragen.</p>
    </div>

    <v-order-item-list :errors="errors" :data='@json((isset($order) ? $order->orderItems : []))' :persons='@json($persons ?? [])'></v-order-item-list>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-order-item-list-template">
        <div class="flex flex-col gap-4">
            <x-admin::table>
                <x-admin::table.thead>
                    <x-admin::table.thead.tr>
                        <x-admin::table.th class="min-w-[300px] w-2/5">Product</x-admin::table.th>
                        <x-admin::table.th class="min-w-[150px] w-1/5">Persoon</x-admin::table.th>
                        <x-admin::table.th class="text-center">Aantal</x-admin::table.th>
                        <x-admin::table.th class="text-center">Totaal</x-admin::table.th>
                        <x-admin::table.th class="text-center">Status</x-admin::table.th>
                        <x-admin::table.th>Planning</x-admin::table.th>
                        <x-admin::table.th class="text-center">Actie</x-admin::table.th>
                    </x-admin::table.thead.tr>
                </x-admin::table.thead>
                <x-admin::table.tbody>
                    <template v-for="(item, index) in items" :key="index">
                        <v-order-item :item="item" :index="index" :errors="errors" :persons="persons" @onRemoveItem="removeItem($event)"></v-order-item>
                    </template>
                </x-admin::table.tbody>
            </x-admin::table>

            <span class="text-md flex max-w-max cursor-pointer items-center gap-2 text-brandColor" @click="addItem">Regel toevoegen</span>
        </div>
    </script>

    <script type="text/x-template" id="v-order-item-template">
        <x-admin::table.thead.tr>
            <x-admin::table.td class="min-w-[300px] w-2/5">
                @include('adminc.components.product-selector')
                <v-product-selector
                    :name="`${inputName}[product_id]`"
                    label=""
                    placeholder="Zoek product..."
                    :current-value="item.product_id"
                    :current-label="item.product_name || (item.product && (item.product.name_with_path || item.product.name)) || ''"
                    :can-add-new="false"
                    :multiple="false"
                    @change="(p) => selectProduct(p)"
                    @update:value="(id) => { item.product_id = id; }"
                />
            </x-admin::table.td>
            <x-admin::table.td>
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.control
                        type="select"
                        ::name="`${inputName}[person_id]`"
                        ::value="item.person_id"
                        rules=""
                        ::errors="errors"
                        label="Persoon"
                        placeholder="Selecteer persoon"
                        @on-change="(e) => item.person_id = e.value"
                        position="center"
                    >
                        <option value="">Selecteer persoon</option>
                        <option v-for="(personName, personId) in persons" :key="personId" :value="personId" :selected="item.person_id == personId">
                            @{{ personName }}
                        </option>
                    </x-admin::form.control-group.control>
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.control type="inline" ::name="`${inputName}[quantity]`" ::value="item.quantity" rules="required|integer|min:1" ::errors="errors" label="Aantal" placeholder="Aantal" @on-change="(e) => updateQuantity(e.value)" position="center" />
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.control type="inline" ::name="`${inputName}[total_price]`" ::value="item.total_price" rules="required|decimal:2" ::errors="errors" label="Totaal" placeholder="Totaal" @on-change="(e) => item.total_price = e.value" position="center" />
                </x-admin::form.control-group>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 text-center">
                <span v-if="item.status" class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full" :class="getStatusClass(item.status)">
                    @{{ getStatusLabel(item.status) }}
                </span>
                <span v-else class="text-gray-400 text-xs">-</span>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2">
                <div v-if="item.planning_summary" class="text-xs text-gray-700 dark:text-gray-300">
                    <div v-for="(booking, idx) in item.planning_summary" :key="idx" class="mb-1">
                        <strong>@{{ booking.resource }}</strong><br>
                        @{{ booking.from }} - @{{ booking.to }}
                    </div>
                </div>
                <span v-else class="text-gray-400 text-xs">
                    <span v-if="item.canPlan">Niet ingepland</span>
                    <span v-else>Niet planbaar</span>
                </span>
            </x-admin::table.td>
            <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                <div class="flex items-center justify-end gap-2">
                    <i @click="removeItem" class="icon-delete cursor-pointer text-2xl"></i>
                </div>
            </x-admin::table.td>
        </x-admin::table.thead.tr>
    </script>

    <script type="module">
        app.component('v-order-item-list', {
            template: '#v-order-item-list-template',
            props: ['errors', 'data', 'persons'],
            data() {
                return {
                    items: this.data && this.data.length ? this.data.map(r => {
                        console.log('Processing order item data:', r);
                        // Get product name with path if available
                        const productName = (r.product && r.product.name_with_path)
                            || (r.product && r.product.name)
                            || r.product_name
                            || null;
                        return {
                            id: r.id ?? null,
                            product_id: r.product_id ?? null,
                            product_name: productName,
                            person_id: r.person_id ?? null,
                            quantity: r.quantity ?? 1,
                            total_price: r.total_price ?? 0,
                            status: r.status ?? null,
                            // include product and partner products info if present (server eager-loaded)
                            product: r.product || null,
                            partner_product_count: (r.product && Array.isArray(r.product.partner_products)) ? r.product.partner_products.length : 0,
                            planning_summary: this.formatPlanningSummary(r.resource_order_items || []),
                            canPlan: r.can_plan === '1' || r.can_plan === true || false,
                        };
                    }) : [{ id: null, product_id: null, product_name: null, person_id: null, quantity: 1, total_price: 0, status: null, product: null, partner_product_count: 0, planning_summary: null, canPlan: false }],
                };
            },
            methods: {
                addItem() {
                    this.items.push({ id: null, product_id: null, product_name: null, person_id: null, quantity: 1, total_price: 0, status: null, product: null, partner_product_count: 0, planning_summary: null, canPlan: false});
                },
                removeItem(item) {
                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            if (this.items.length === 1) {
                                this.items = [{ id: null, product_id: null, product_name: null, person_id: null, quantity: 1, total_price: 0, status: null, product: null, partner_product_count: 0, planning_summary: null , canPlan: false}];
                            } else {
                                const index = this.items.indexOf(item);
                                if (index !== -1) this.items.splice(index, 1);
                            }
                        },
                    });
                },
                formatPlanningSummary(bookings) {
                    if (!bookings || !bookings.length) return null;
                    return bookings.map(b => ({
                        resource: b.resource?.name || 'Onbekend',
                        from: this.formatDateTime(b.from),
                        to: this.formatDateTime(b.to)
                    }));
                },
                formatDateTime(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr);
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${day}-${month}-${year} ${hours}:${minutes}`;
                },
            },
        });

        app.component('v-order-item', {
            template: '#v-order-item-template',
            props: ['index', 'item', 'errors', 'persons'],
            data() {
                return {
                    productPrice: null, // Store the product price for calculations
                };
            },
            computed: {
                inputName() {
                    return this.item.id ? `items[${this.item.id}]` : `items[item_${this.index}]`;
                },
            },
            methods: {
                selectProduct(result) {
                    try { console.log('[v-order-item] selectProduct', result); } catch (e) {}
                    if (result) {
                        this.item.product_id = result.id || result;
                        // Use name_with_path if available, otherwise fallback to name
                        this.item.product_name = result.name_with_path || result.name || '';
                        // Store product price for quantity updates
                        this.productPrice = result.price ? parseFloat(result.price) : null;
                        // Update total_price when a product is selected or changed
                        if (this.productPrice) {
                            const quantity = this.item.quantity || 1;
                            this.item.total_price = this.productPrice * quantity;
                        }
                    } else {
                        this.item.product_id = null;
                        this.item.product_name = '';
                        this.item.total_price = 0;
                        this.productPrice = null;
                    }
                    console.log('Updated item after product selection:', this.item);
                },
                updateQuantity(quantity) {
                    this.item.quantity = quantity;
                    // Recalculate total_price if product price is available
                    if (this.productPrice) {
                        this.item.total_price = this.productPrice * quantity;
                    }
                },
                removeItem() {
                    try { console.log('[v-order-item] removeItem'); } catch (e) {}
                    this.$emit('onRemoveItem', this.item);
                },
                getStatusLabel(status) {
                    const labels = {
                        'new': 'Nieuw',
                        'planned': 'Ingepland'
                    };
                    return labels[status] || status;
                },
                getStatusClass(status) {
                    const classes = {
                        'new': 'bg-neutral-bg text-gray-800',
                        'planned': 'bg-green-100 text-green-800'
                    };
                    return classes[status] || 'bg-neutral-bg text-gray-800';
                },
            },
        });
    </script>
@endPushOnce
