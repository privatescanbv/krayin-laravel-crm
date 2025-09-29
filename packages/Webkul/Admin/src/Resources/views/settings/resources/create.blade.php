<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.resources.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.resources.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.resources" />

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

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.resources.index.create.resource_type')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="select"
                        name="resource_type_id"
                        rules="required|numeric"
                        :label="trans('admin::app.settings.resources.index.create.resource_type')"
                    >
                        <option value="">@lang('admin::app.select')</option>
                        @foreach ($resourceTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>

                    <x-admin::form.control-group.error control-name="resource_type_id" />
                </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">
                    @lang('admin::app.settings.resources.index.create.clinic')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="select"
                    name="clinic_id"
                    rules="required|numeric"
                    :label="trans('admin::app.settings.resources.index.create.clinic')"
                >
                    <option value="">@lang('admin::app.select')</option>
                    @foreach ($clinics as $clinic)
                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                    @endforeach
                </x-admin::form.control-group.control>

                <x-admin::form.control-group.error control-name="clinic_id" />
            </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.resources.index.create.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        rules="required|min:1|max:100"
                        :label="trans('admin::app.settings.resources.index.create.name')"
                        :placeholder="trans('admin::app.settings.resources.index.create.name')"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

