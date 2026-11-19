<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.document_types.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.document_types.update', $document_type->id)" method="POST">
        @method('PUT')
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.document_types.edit" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.document_types.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.document_types.index.edit.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @if ($document_type->key)
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        @lang('admin::app.settings.document_types.index.edit.key'): <code>{{ $document_type->key }}</code>
                    </p>
                @endif

                <x-adminc::components.field
                    type="text"
                    name="name"
                    value="{{ old('name', $document_type->name) }}"
                    rules="required|min:1|max:100"
                    :label="trans('admin::app.settings.document_types.index.edit.name')"
                    :placeholder="trans('admin::app.settings.document_types.index.edit.name')"
                />

                <x-adminc::components.field
                    type="textarea"
                    name="description"
                    value="{{ old('description', $document_type->description) }}"
                    :label="trans('admin::app.settings.document_types.index.edit.description')"
                    :placeholder="trans('admin::app.settings.document_types.index.edit.description')"
                />

                <x-adminc::components.field
                    type="number"
                    name="sort_order"
                    value="{{ old('sort_order', $document_type->sort_order) }}"
                    :label="trans('admin::app.settings.document_types.index.edit.sort_order')"
                />

                <x-adminc::components.field
                    type="switch"
                    name="show_in_clinic_portal"
                    value="1"
                    :checked="(bool) old('show_in_clinic_portal', $document_type->show_in_clinic_portal)"
                    :label="trans('admin::app.settings.document_types.index.edit.show_in_clinic_portal')"
                />

                <x-adminc::components.field
                    type="switch"
                    name="show_in_patient_portal"
                    value="1"
                    :checked="(bool) old('show_in_patient_portal', $document_type->show_in_patient_portal)"
                    :label="trans('admin::app.settings.document_types.index.edit.show_in_patient_portal')"
                />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
