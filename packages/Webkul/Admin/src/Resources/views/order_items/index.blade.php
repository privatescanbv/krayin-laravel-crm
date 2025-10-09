<x-admin::layouts>
    <x-slot:title>
        Orderregels
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.clinics" />

                <div class="text-xl font-bold dark:text-gray-300">
                    Orderregels
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.order_items.create'))
                    <a href="{{ route('admin.order_items.create') }}" class="primary-button">
                        Nieuw
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.order_items.index')" ref="datagrid" />
    </div>

</x-admin::layouts>

