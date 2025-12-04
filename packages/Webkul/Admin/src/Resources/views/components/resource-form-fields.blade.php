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
<x-adminc::components.field
    type="text"
    name="name"
    :label="trans('admin::app.settings.resources.index.create.name')"
    value="{{ old('name', $resource->name ?? '') }}"
    rules="required|min:1|max:100"
    :placeholder="trans('admin::app.settings.resources.index.create.name')"
/>

<!-- Resource Type -->
<x-adminc::components.field
    type="select"
    name="resource_type_id"
    :label="trans('admin::app.settings.resources.index.create.resource_type')"
    value="{{ old('resource_type_id', $resource->resource_type_id ?? '') }}"
    rules="required|numeric"
>
    <option value="">@lang('admin::app.select')</option>
    @foreach ($resourceTypes as $type)
        <option value="{{ $type->id }}" @selected(old('resource_type_id', $resource->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
    @endforeach
</x-adminc::components.field>

<!-- Clinic -->
<x-adminc::components.field
    type="select"
    name="clinic_id"
    :label="trans('admin::app.settings.resources.index.create.clinic')"
    value="{{ $selectedClinicId }}"
    rules="required|numeric"
>
    <option value="">@lang('admin::app.select')</option>
    @foreach ($clinics as $clinic)
        <option value="{{ $clinic->id }}" @selected($selectedClinicId == $clinic->id)>{{ $clinic->name }}</option>
    @endforeach
</x-adminc::components.field>

<!-- Is Active -->
<x-adminc::components.field
    type="switch"
    name="is_active"
    label="Actief"
    value="1"
    :checked="(bool) old('is_active', $resource->is_active ?? true)"
/>

<!-- Notes -->
<x-adminc::components.field
    type="textarea"
    name="notes"
    :label="trans('admin::app.settings.resources.index.create.notes')"
    value="{{ old('notes', $resource->notes ?? '') }}"
    :placeholder="trans('admin::app.settings.resources.index.create.notes')"
    rows="4"
/>
