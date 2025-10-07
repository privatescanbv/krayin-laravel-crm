<x-admin::layouts>
    <x-slot:title>
        Import Runs
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.import-runs" />

                <div class="text-xl font-bold dark:text-gray-300">
                    Import Runs
                </div>
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.import-runs.index')" ref="datagrid" />
    </div>

</x-admin::layouts>