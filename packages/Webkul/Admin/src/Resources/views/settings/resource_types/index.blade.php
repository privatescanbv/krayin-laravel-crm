<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.resource_types.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.resource_types" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.settings.resource_types.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.resource_types.create'))
                    <a href="{{ route('admin.settings.resource_types.create') }}" class="primary-button">
                        @lang('admin::app.settings.resource_types.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.resource_types.index')" ref="datagrid" />
    </div>

</x-admin::layouts>

