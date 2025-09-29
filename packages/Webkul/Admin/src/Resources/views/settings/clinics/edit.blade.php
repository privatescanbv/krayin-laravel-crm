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

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.clinics.index.create.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        value="{{ old('name', $clinic->name) }}"
                        rules="required|min:1|max:100"
                        :label="trans('admin::app.settings.clinics.index.create.name')"
                        :placeholder="trans('admin::app.settings.clinics.index.create.name')"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.clinics.index.create.department')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="department"
                        value="{{ old('department', $clinic->department) }}"
                        rules="max:100"
                        :label="trans('admin::app.settings.clinics.index.create.department')"
                        :placeholder="trans('admin::app.settings.clinics.index.create.department')"
                    />

                    <x-admin::form.control-group.error control-name="department" />
                </x-admin::form.control-group>

                <!-- Emails -->
                @php
                    $__emailsVal = old('emails', $clinic->emails ?? []);
                    if (!is_array($__emailsVal)) { $__emailsVal = []; }
                @endphp
                @include('admin::leads.common.sections.emails', ['name' => 'emails', 'value' => $__emailsVal, 'widthClass' => 'w-full'])

                <!-- Phones -->
                @php
                    $__phonesVal = old('phones', $clinic->phones ?? []);
                    if (!is_array($__phonesVal)) { $__phonesVal = []; }
                @endphp
                @include('admin::leads.common.sections.phones', ['name' => 'phones', 'value' => $__phonesVal, 'widthClass' => 'w-full'])
            </div>

            <!-- Address Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        @lang('admin::app.contacts.organizations.create.address')
                    </h3>
                </div>

                @include('admin::components.address', ['entity' => $clinic->address ?? null])
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

