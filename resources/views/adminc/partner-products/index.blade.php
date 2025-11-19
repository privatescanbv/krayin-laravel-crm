<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.partner_products.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="partner_products" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.partner_products.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('partner_products.create'))
                    <a href="{{ route('admin.partner_products.create') }}" class="primary-button">
                        @lang('admin::app.partner_products.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.partner_products.index')" ref="datagrid" />
    </div>

</x-admin::layouts>

