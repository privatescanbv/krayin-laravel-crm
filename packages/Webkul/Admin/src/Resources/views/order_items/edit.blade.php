<x-admin::layouts>
    <x-slot:title>
        Orderitem bewerken
    </x-slot>

    <x-admin::form :action="route('admin.order_items.update', ['id' => $order_items->id])" method="POST">
        <input type="hidden" name="_method" value="put">
        @include('adminc.components.validation-errors')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.clinics" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Orderitem bewerken
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.orders.edit', ['id' => $order_items->order_id]) }}" class="secondary-button">
                        Annuleren
                    </a>

                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-adminc::components.field
                    type="select"
                    name="status"
                    label="Status"
                    rules="required"
                    value="{{ $order_items->status?->value }}"
                >
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($order_items->status?->value === $value)>{{ $label }}</option>
                    @endforeach
                </x-adminc::components.field>

                <x-adminc::components.field
                    type="text"
                    name="name"
                    label="Naam (overschrijft productnaam)"
                    value="{{ $order_items->name }}"
                    rules=""
                />

                <x-adminc::components.field
                    type="textarea"
                    name="description"
                    label="Omschrijving (overschrijft productomschrijving)"
                    value="{{ $order_items->description }}"
                    rules=""
                />

                <input type="hidden" name="order_id" value="{{ $order_items->order_id }}">

                @include('adminc.components.product-selector')

                <x-adminc::components.selector-field label="Product" :required="true" name="product_id">
                    <v-product-selector
                        name="product_id"
                        placeholder="Zoek product..."
                        :current-value="{{ $order_items->product_id ?? 'null' }}"
                        current-label=""
                        :can-add-new="false"
                        :multiple="false"
                    />
                </x-adminc::components.selector-field>

                <x-adminc::components.field
                    type="select"
                    name="product_type_id"
                    label="Product type (overschrijft product type)"
                    value="{{ old('product_type_id', $order_items->product_type_id ?? $order_items->product?->product_type_id ?? '') }}"
                    rules=""
                >
                    <option value="">@lang('admin::app.select')</option>
                    @foreach ($productTypes as $type)
                        <option
                            value="{{ $type->id }}"
                            @selected((string) old('product_type_id', $order_items->product_type_id ?? $order_items->product?->product_type_id ?? '') === (string) $type->id)
                        >{{ $type->name }}</option>
                    @endforeach
                </x-adminc::components.field>

                @include('adminc.components.contact-person-selector')

                <x-adminc::components.selector-field label="Persoon" :required="true" name="person_id">
                    <v-contact-person-selector
                        name="person_id"
                        label=""
                        placeholder="Zoek persoon..."
                        :current-value="{{ $order_items->person_id ?? 'null' }}"
                        current-label="{{ $order_items->person?->name ?? '' }}"
                        :can-add-new="false"
                    />
                </x-adminc::components.selector-field>

                <div class="flex gap-4">
                    <div class="w-28">
                        <x-adminc::components.field
                            type="number"
                            name="quantity"
                            label="Aantal"
                            value="{{ $order_items->quantity }}"
                            rules="required|integer|min:1"
                        />
                    </div>

                    <div class="flex-1">
                        <x-adminc::components.field
                            type="number"
                            name="total_price"
                            label="Totale prijs"
                            value="{{ $order_items->total_price }}"
                            step="0.01"
                        />
                    </div>

                    <div class="w-40">
                        <x-adminc::components.field
                            type="select"
                            name="currency"
                            label="Valuta"
                            value="{{ old('currency', $order_items->currency ?? $defaultCurrency) }}"
                            rules=""
                        >
                            @foreach ($currencies as $currency)
                                <option
                                    value="{{ $currency['code'] }}"
                                    @selected(old('currency', $order_items->currency ?? $defaultCurrency) === $currency['code'])
                                >{{ $currency['label'] }}</option>
                            @endforeach
                        </x-adminc::components.field>
                    </div>
                </div>



                <x-adminc::components.purchase-price-fields
                    :purchase-price="$order_items->purchasePrice"
                />

                <x-adminc::components.purchase-price-fields
                    :purchase-price="$order_items->invoicePurchasePrice"
                    field-prefix="invoice_"
                    title="Factuur inkoopprijzen"
                    total-id="invoice-purchase-price-total"
                    :labels="[
                        'misc'        => 'Overig',
                        'doctor'      => 'Arts',
                        'cardiology'  => 'Cardiologie',
                        'clinic'      => 'Kliniek',
                        'radiology'   => 'Radiologie',
                    ]"
                />
            </div>
        </div>
    </x-admin::form>

    @push('scripts')
    <script type="module">
        document.addEventListener('adminc:product-selected', async function(e) {
            if (e.detail.fieldName !== 'product_id' || !e.detail.id) return;

            try {
                const response = await axios.get(`/admin/order-items/partner-purchase-prices/${e.detail.id}`);
                const prices = response.data;

                const productTypeSelect = document.querySelector(`select[name="product_type_id"]`);
                if (productTypeSelect) {
                    productTypeSelect.value = prices.product_type_id ?? '';
                    productTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const suffixes = ['misc', 'doctor', 'cardiology', 'clinic', 'radiology'];
                suffixes.forEach(suffix => {
                    const input = document.querySelector(`input[name="purchase_price_${suffix}"]`);
                    if (input) {
                        const value = new Intl.NumberFormat('nl-NL', {
                            minimumFractionDigits: 2, maximumFractionDigits: 2
                        }).format(prices[suffix] ?? 0);
                        input.value = value;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            } catch (err) {
                console.warn('Failed to fetch partner purchase prices', err);
            }
        });
    </script>
    @endpush
</x-admin::layouts>

