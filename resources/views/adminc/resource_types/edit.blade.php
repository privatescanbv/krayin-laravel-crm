<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.resource_types.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.resource_types.update', $resource_type->id)" method="POST">
        @method('PUT')
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.resource_types.edit" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.resource_types.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.resource_types.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.resource_types.index.create.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        value="{{ old('name', $resource_type->name) }}"
                        rules="required|min:1|max:100"
                        :label="trans('admin::app.settings.resource_types.index.create.name')"
                        :placeholder="trans('admin::app.settings.resource_types.index.create.name')"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.resource_types.index.create.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        :value="old('description', $resource_type->description)"
                    />
                    <x-admin::form.control-group.error control-name="description" />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

