<x-admin::layouts>
    <x-slot:title>
        Order bewerken
    </x-slot>

    <x-admin::form :action="route('admin.settings.orders.update', ['id' => $orders->id])" method="POST">
        <input type="hidden" name="_method" value="put">

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.index" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Order bewerken
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Titel</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="text" name="title" :value="$orders->title" rules="required" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Sales Order ID</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="text" name="sales_order_id" :value="$orders->sales_order_id" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>Totale prijs</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="number" step="0.01" name="total_price" :value="$orders->total_price" />
            </x-admin::form.control-group>
        </div>
    </x-admin::form>
</x-admin::layouts>

