<x-admin::layouts>
    <x-slot:title>
        Orderitem aanmaken
    </x-slot>

    <x-admin::form :action="route('admin.order_items.store')" method="POST">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.clinics" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Orderitem aanmaken
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
                rules="required|integer"
            />

            <x-adminc::components.field
                type="number"
                name="product_id"
                label="Product ID"
                rules="required|integer"
            />

            <x-adminc::components.field
                type="select"
                name="person_id"
                label="Persoon"
                rules="required|integer|exists:persons,id"
            >
                <option value="">Selecteer persoon</option>
                @foreach ($persons as $personId => $personName)
                    <option value="{{ $personId }}">{{ $personName }}</option>
                @endforeach
            </x-adminc::components.field>

            <x-adminc::components.field
                type="number"
                name="quantity"
                label="Aantal"
                rules="required|integer|min:1"
            />

            <x-adminc::components.field
                type="number"
                name="total_price"
                label="Totale prijs"
                step="0.01"
            />
        </div>
    </x-admin::form>
</x-admin::layouts>

