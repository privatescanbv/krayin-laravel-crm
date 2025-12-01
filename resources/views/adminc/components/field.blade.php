@props([
    'label' => '',
    'name' => '',
    'value' => '',
    'readonly' => false,
    'placeholder' => '',
    'type' => 'text',
    'class' => '',
])
<x-admin::form.control-group class="{{ $class }}">
    <x-admin::form.control-group.control
        type="{{ $type }}"
        name="{{ $name ?: $label }}"
        value="{{ $value }}"
        label="{{ $label ?: $name }}"
        placeholder="{{ $placeholder }}"
        :readonly="$readonly" />

    <x-admin::form.control-group.label
        :switch="$type === 'switch'">
        {{ $label ?: $name }}
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.error control-name="{{ $name }}" />
</x-admin::form.control-group>
