<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.resources.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.resources.update', $resource->id)" method="POST">
        @method('PUT')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.resources.edit" :entity="$resource" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.resources.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.settings.resources.shifts.index', $resource->id) }}" class="secondary-button">
                        @lang('admin::app.settings.resources.index.manage-shifts')
                    </a>
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.resources.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::resource-form-fields :resource="$resource" />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

