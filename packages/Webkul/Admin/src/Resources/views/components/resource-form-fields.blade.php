@php use App\Models\ResourceType;use App\Repositories\ClinicDepartmentRepository; @endphp
@props([
    'resource' => null,
    'preSelectedDepartmentId' => null,
    'preSelectedClinicId' => null,
])

@php
    $resourceTypes = ResourceType::orderBy('name')->get(['id', 'name']);
    $departments = app(ClinicDepartmentRepository::class)->with(['clinic'])->all();

    // Pre-select department if provided
    $selectedDeptId = old('clinic_department_id', $resource->clinic_department_id ?? '');
    if (empty($selectedDeptId) && isset($preSelectedDepartmentId)) {
        $selectedDeptId = $preSelectedDepartmentId;
    }
    // Fallback: if a clinic_id is pre-selected (from clinic view), find the default department
    if (empty($selectedDeptId) && isset($preSelectedClinicId)) {
        $defaultDept = $departments->first(fn($d) => $d->clinic_id == $preSelectedClinicId && $d->name === 'Standaard')
            ?? $departments->first(fn($d) => $d->clinic_id == $preSelectedClinicId);
        if ($defaultDept) {
            $selectedDeptId = $defaultDept->id;
        }
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

<!-- Afdeling: value moet mee naar v-field (anders overschrijft VeeValidate de server-side selected state) -->
<x-adminc::components.field
    type="select"
    name="clinic_department_id"
    label="Afdeling"
    value="{{ $selectedDeptId }}"
    rules="required|numeric"
>
    <option value="">@lang('admin::app.select')</option>
    @foreach ($departments as $dept)
        <option value="{{ $dept->id }}" @selected($selectedDeptId == $dept->id)>
            {{ $dept->clinic->name }} — {{ $dept->name }}
        </option>
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

<!-- Allow Outside Availability -->
<x-adminc::components.field
    type="switch"
    name="allow_outside_availability"
    label="Plannen buiten beschikbaarheid toestaan"
    value="1"
    :checked="(bool) old('allow_outside_availability', $resource->allow_outside_availability ?? false)"
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
