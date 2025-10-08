<x-admin::layouts>
    <x-slot:title>
        Orders
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.index" />

                <div class="text-xl font-bold dark:text-gray-300">
                    Orders
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.orders.create'))
                    <a href="{{ route('admin.settings.orders.create') }}" class="primary-button">
                        @lang('admin::app.settings.clinics.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.orders.index')" ref="datagrid" />
    </div>

</x-admin::layouts>

