<x-admin::layouts>
    <x-slot:title>
        Import Runs - Reports
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.import-runs" />

                <div class="text-xl font-bold dark:text-gray-300">
                    Import Run Reports
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Overzicht van alle SugarCRM import runs met errors en warnings
                </p>
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.import-runs.index')" ref="datagrid" />
    </div>

</x-admin::layouts>
