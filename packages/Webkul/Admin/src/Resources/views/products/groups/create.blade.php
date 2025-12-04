<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.productgroups.create.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.productgroups.store')"
        method="POST"
    >
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.productgroups.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button
                        type="submit"
                        class="primary-button"
                    >
                        @lang('admin::app.productgroups.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-adminc::components.field
                    type="text"
                    name="name"
                    :label="trans('admin::app.productgroups.create.name')"
                    value="{{ old('name') }}"
                    rules="required"
                    :placeholder="trans('admin::app.productgroups.create.name')"
                />

                <x-adminc::components.field
                    type="textarea"
                    name="description"
                    :label="trans('admin::app.productgroups.create.description')"
                    value="{{ old('description') }}"
                    :placeholder="trans('admin::app.productgroups.create.description')"
                />

                <x-adminc::components.field
                    type="select"
                    name="parent_id"
                    :label="trans('admin::app.productgroups.create.parent')"
                    value="{{ old('parent_id') }}"
                >
                    <option value="">@lang('admin::app.productgroups.create.select-parent')</option>
                    @foreach ($productGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->path }}</option>
                    @endforeach
                </x-adminc::components.field>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
