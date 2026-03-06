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

                @include('adminc.components.contact-person-selector')

                <x-adminc::components.selector-field label="Persoon" :required="true" name="person_id">
                    <v-contact-person-selector
                        name="person_id"
                        placeholder="Zoek persoon..."
                        :current-value="{{ $order_items->person_id ?? 'null' }}"
                        current-label="{{ $order_items->person?->name ?? '' }}"
                        :can-add-new="false"
                    />
                </x-adminc::components.selector-field>

                <x-adminc::components.field
                    type="number"
                    name="quantity"
                    label="Aantal"
                    value="{{ $order_items->quantity }}"
                    rules="required|integer|min:1"
                />

                <x-adminc::components.field
                    type="number"
                    name="total_price"
                    label="Totale prijs"
                    value="{{ $order_items->total_price }}"
                    step="0.01"
                />

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



                <x-adminc::components.purchase-price-fields
                    :purchase-price="$order_items->purchasePrice"
                />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

