<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.clinics.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.clinics" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.settings.clinics.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.clinics.create'))
                    <a href="{{ route('admin.settings.clinics.create') }}" class="primary-button">
                        @lang('admin::app.settings.clinics.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.clinics.index')" ref="datagrid" />
    </div>

</x-admin::layouts>

