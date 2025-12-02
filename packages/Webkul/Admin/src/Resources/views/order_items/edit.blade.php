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
                    <x-admin::breadcrumbs name="settings.index" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Orderitem bewerken
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <x-adminc::components.field
                type="number"
                name="order_id"
                label="Order ID"
                value="{{ $order_items->order_id }}"
                rules="required|integer"
            />

            <x-adminc::components.field
                type="number"
                name="product_id"
                label="Product ID"
                value="{{ $order_items->product_id }}"
                rules="required|integer"
            />

            <x-adminc::components.field
                type="select"
                name="person_id"
                label="Persoon"
                value="{{ $order_items->person_id }}"
                rules="required|integer|exists:persons,id"
            >
                <option value="">Selecteer persoon</option>
                @foreach ($persons as $personId => $personName)
                    <option value="{{ $personId }}" {{ $order_items->person_id == $personId ? 'selected' : '' }}>{{ $personName }}</option>
                @endforeach
            </x-adminc::components.field>

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
        </div>
    </x-admin::form>
</x-admin::layouts>

