<x-admin::layouts>
    <x-slot:title>
        Orderregel bewerken
    </x-slot>

    <x-admin::form :action="route('admin.settings.order_regels.update', ['id' => $order_regels->id])" method="POST">
        <input type="hidden" name="_method" value="put">

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.index" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Orderregel bewerken
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Order ID</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="number" name="order_id" :value="$order_regels->order_id" rules="required|integer" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Product ID</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="number" name="product_id" :value="$order_regels->product_id" rules="required|integer" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Aantal</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="number" name="quantity" :value="$order_regels->quantity" rules="required|integer|min:1" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Totale prijs</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="number" step="0.01" name="total_price" :value="$order_regels->total_price" />
            </x-admin::form.control-group>
        </div>
    </x-admin::form>
</x-admin::layouts>

