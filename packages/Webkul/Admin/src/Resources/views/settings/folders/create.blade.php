<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.folders.create.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <!-- breadcrumbs -->
                <x-admin::breadcrumbs
                    name="settings.folders.create"
                />

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.settings.folders.create.title')
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <form
                method="POST"
                action="{{ route('admin.settings.folders.store') }}"
                @submit="onSubmit"
            >
                @csrf

                <div class="p-6">
                    <div class="grid gap-6">
                        <!-- Name -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.settings.folders.create.name')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                name="name"
                                id="name"
                                rules="required"
                                :label="trans('admin::app.settings.folders.create.name')"
                                :placeholder="trans('admin::app.settings.folders.create.name')"
                            />

                            <x-admin::form.control-group.error control-name="name" />
                        </x-admin::form.control-group>

                        <!-- Parent Folder -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                @lang('admin::app.settings.folders.create.parent')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="select"
                                name="parent_id"
                                id="parent_id"
                                :label="trans('admin::app.settings.folders.create.parent')"
                            >
                                <option value="">@lang('admin::app.settings.folders.create.no-parent')</option>
                                @foreach ($folders as $folder)
                                    <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                                @endforeach
                            </x-admin::form.control-group.control>

                            <x-admin::form.control-group.error control-name="parent_id" />
                        </x-admin::form.control-group>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <a
                        href="{{ route('admin.settings.folders.index') }}"
                        class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                    >
                        @lang('admin::app.settings.folders.create.cancel')
                    </a>

                    <x-admin::button
                        type="submit"
                        class="primary-button"
                        :title="trans('admin::app.settings.folders.create.save-btn')"
                    />
                </div>
            </form>
        </div>
    </div>
</x-admin::layouts>