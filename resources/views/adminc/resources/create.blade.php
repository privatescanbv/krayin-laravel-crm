<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.resources.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.resources.store')" method="POST">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.resources.create" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.resources.index.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.resources.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
               <input type="hidden" name="return_to" value="{{ isset($preSelectedClinicId) ? 'clinic_view' : request()->query('return_to') }}" />
                <x-admin::resource-form-fields :pre-selected-clinic-id="$preSelectedClinicId" />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

