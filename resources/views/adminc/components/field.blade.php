@props([
    'label' => '',
    'name' => '',
    'value' => '',
    'readonly' => false,
    'placeholder' => '',
    'type' => 'text',
    'class' => '',
    'rules' => null,
    'labelClass' => '',
])

@php
    // Bepaal control name (voor errors) en label tekst
    $controlName = $name ?: \Illuminate\Support\Str::slug($label, '_');
    $labelText = $label ?: $name;

    // Required class uit rules halen als die nog niet expliciet is gezet.
    $computedLabelClass = $labelClass;
    if (! $computedLabelClass && $rules && \Illuminate\Support\Str::contains($rules, 'required')) {
        $computedLabelClass = 'required';
    }
@endphp

<x-admin::form.control-group class="{{ $class }}">
    <x-admin::form.control-group.control
        type="{{ $type }}"
        name="{{ $name ?: $label }}"
        value="{{ $value }}"
        label="{{ $labelText }}"
        placeholder="{{ $placeholder }}"
        :readonly="$readonly"
        :rules="$rules"
        {{-- Geef alle extra attributen (zoals v-model, autocomplete, etc.) door aan de onderliggende control --}}
        {{ $attributes->except(['label', 'name', 'value', 'readonly', 'placeholder', 'type', 'class', 'rules', 'labelClass']) }}
    >
        {{-- Doorlaat slot zodat select/textarea/options gebruikt kunnen worden --}}
        {{ $slot }}
    </x-admin::form.control-group.control>

    <x-admin::form.control-group.label
        :switch="$type === 'switch'"
        class="{{ $computedLabelClass }}"
    >
        {{ $labelText }}
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.error control-name="{{ $controlName }}" />
</x-admin::form.control-group>
