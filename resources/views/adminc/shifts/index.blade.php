<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.shifts.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.resources.shifts" :entity="$resource" />

                <div class="text-xl font-bold dark:text-gray-300">
                    {{ $resource->name }} — @lang('admin::app.settings.shifts.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.settings.resources.show', $resource->id) }}" class="primary-button">
                    Rooster bekijken
                </a>
                <a href="{{ route('admin.settings.resources.shifts.create', $resource->id) }}" class="primary-button">
                    Regel Toevoegen
                </a>
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.settings.resources.shifts.index', $resource->id)" ref="datagrid" />
    </div>
</x-admin::layouts>


