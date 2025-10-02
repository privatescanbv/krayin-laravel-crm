<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.clinics.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.clinics.update', $clinic->id)" method="POST">
        @method('PUT')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.clinics.edit" :entity="$clinic" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.clinics.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.clinics.index.create.save-btn')
                    </button>
                </div>
            </div>

            <x-admin::clinic.form-fields :clinic="$clinic" />
        </div>
    </x-admin::form>
</x-admin::layouts>

