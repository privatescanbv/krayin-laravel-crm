@php use App\Models\ResourceType;use App\Repositories\ClinicRepository; @endphp
@props([
    'resource' => null,
    'preSelectedClinicId' => null,
])

@php
    $resourceTypes = ResourceType::orderBy('name')->get(['id', 'name']);
    $clinics = app(ClinicRepository::class)->allActive(['id', 'name']);
    
    // Pre-select clinic if provided
    $selectedClinicId = old('clinic_id', $resource->clinic_id ?? '');
    if (empty($selectedClinicId) && isset($preSelectedClinicId)) {
        $selectedClinicId = $preSelectedClinicId;
    }
@endphp

<!-- Naam -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.settings.resources.index.create.name')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="text"
        name="name"
        value="{{ old('name', $resource->name ?? '') }}"
        rules="required|min:1|max:100"
        :label="trans('admin::app.settings.resources.index.create.name')"
        :placeholder="trans('admin::app.settings.resources.index.create.name')"
    />

    <x-admin::form.control-group.error control-name="name" />
</x-admin::form.control-group>

<!-- Resource Type -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.settings.resources.index.create.resource_type')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="select"
        name="resource_type_id"
        value="{{ old('resource_type_id', $resource->resource_type_id ?? '') }}"
        rules="required|numeric"
        :label="trans('admin::app.settings.resources.index.create.resource_type')"
    >
        <option value="">@lang('admin::app.select')</option>
        @foreach ($resourceTypes as $type)
            <option value="{{ $type->id }}" @selected(old('resource_type_id', $resource->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
        @endforeach
    </x-admin::form.control-group.control>

    <x-admin::form.control-group.error control-name="resource_type_id" />
</x-admin::form.control-group>

<!-- Clinic -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.settings.resources.index.create.clinic')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="select"
        name="clinic_id"
        value="{{ $selectedClinicId }}"
        rules="required|numeric"
        :label="trans('admin::app.settings.resources.index.create.clinic')"
    >
        <option value="">@lang('admin::app.select')</option>
        @foreach ($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId == $clinic->id)>{{ $clinic->name }}</option>
        @endforeach
    </x-admin::form.control-group.control>

    <x-admin::form.control-group.error control-name="clinic_id" />
</x-admin::form.control-group>
