<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.product_types.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.product_types.update', $product_type->id)" method="POST">
        @method('PUT')
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.product_types.edit" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.product_types.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.product_types.edit.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-adminc::components.field
                    type="text"
                    name="name"
                    value="{{ old('name', $product_type->name) }}"
                    rules="required|min:1|max:100"
                    :label="trans('admin::app.settings.product_types.edit.name')"
                    :placeholder="trans('admin::app.settings.product_types.edit.name')"
                />

                <x-adminc::components.field
                    type="textarea"
                    name="description"
                    value="{{ old('description', $product_type->description) }}"
                    :label="trans('admin::app.settings.product_types.edit.description')"
                    :placeholder="trans('admin::app.settings.product_types.edit.description')"
                />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

