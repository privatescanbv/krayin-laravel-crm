@props([
    'label'    => '',
    'required' => false,
    'name'     => '',
])

<x-admin::form.control-group>
    @if ($label)
        <x-admin::form.control-group.label
            static
            class="{{ $required ? 'required' : '' }}"
        >
            {{ $label }}
        </x-admin::form.control-group.label>
    @endif

    {{ $slot }}

    @if ($name)
        <x-admin::form.control-group.error control-name="{{ $name }}" />
    @endif
</x-admin::form.control-group>
