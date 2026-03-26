<x-admin::layouts>
    <x-slot:title>
        Afdelingen
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-gray-300">
                    Afdelingen
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.clinics.create'))
                    <a href="{{ route('admin.clinic_departments.create') }}" class="primary-button">
                        Afdeling aanmaken
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.clinic_departments.index')" ref="datagrid" />
    </div>
</x-admin::layouts>
