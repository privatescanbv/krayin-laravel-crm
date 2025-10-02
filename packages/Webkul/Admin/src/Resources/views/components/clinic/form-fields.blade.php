@props([
    'clinic' => null,
])

<!-- Basic Information Section -->
<div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.clinics.index.create.name')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="text"
            name="name"
            value="{{ old('name', $clinic->name ?? '') }}"
            rules="required|min:1|max:100"
            :label="trans('admin::app.settings.clinics.index.create.name')"
            :placeholder="trans('admin::app.settings.clinics.index.create.name')"
        />

        <x-admin::form.control-group.error control-name="name" />
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            AFB naam kliniek
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="text"
            name="registration_form_clinic_name"
            value="{{ old('registration_form_clinic_name', $clinic->registration_form_clinic_name ?? '') }}"
            rules="max:255"
            label="AFB naam kliniek"
            placeholder="AFB naam kliniek"
        />

        <x-admin::form.control-group.error control-name="registration_form_clinic_name" />
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
