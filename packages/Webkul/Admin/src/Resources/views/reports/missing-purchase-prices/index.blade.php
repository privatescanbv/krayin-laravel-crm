<x-admin::layouts>
    <x-slot:title>
        Orders zonder inkoopprijs
    </x-slot>

    <div class="mb-5 grid gap-1.5">
        <p class="text-2xl font-semibold dark:text-white">Orders zonder inkoopprijs</p>
        <p class="text-gray-600 dark:text-gray-300">
            Orders met gewonnen orderregels waarvan alle inkoopprijzen 0 zijn. Tijdelijk overzicht.
        </p>
    </div>

    <x-admin::datagrid :src="route('admin.reports.missing-purchase-prices.index')" ref="datagrid" />
</x-admin::layouts>
