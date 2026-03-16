@props([
    'label' => '',
    'name' => '',
    'value' => '',
    'readonly' => false,
    'type' => 'text',
    'class' => '',
    'rules' => null,
    'labelClass' => '',
    'focus' => false,
    'focus' => null,
    'autocomplete' => false,
    'errorName' => null,
])

@php
    // Bepaal control name (voor errors) en label tekst
    $controlName = $errorName ?: ($name ?: \Illuminate\Support\Str::slug($label, '_'));
    $labelText = $label ?: $name;

    // Required class uit rules halen als die nog niet expliciet is gezet.
    $computedLabelClass = $labelClass;
    if (! $computedLabelClass && $rules && \Illuminate\Support\Str::contains($rules, 'required')) {
        $computedLabelClass = 'required';
    }
    $isGroupedControl = in_array($type, ['group', 'radio-group'], true);
    $useStaticLabel = in_array($type, ['select', 'multiselect', 'textarea', 'datetime', 'date'], true);

    $resolvedFocus = $type != 'hidden' && !$readonly && $focus;
    $resolvedAutoComplete = $autocomplete && $type != 'hidden' && !$readonly;
@endphp

<x-admin::form.control-group class="{{ $class }}">
    @if (! $isGroupedControl && $labelText && $useStaticLabel)
        <x-admin::form.control-group.label :static="true" class="{{ $computedLabelClass }}">
            {{ $labelText }}
        </x-admin::form.control-group.label>
    @endif
    <x-admin::form.control-group.control
        type="{{ $type }}"
        name="{{ $name ?: $label }}"
        value="{{ html_entity_decode($value ?? '', ENT_QUOTES, 'UTF-8') }}"
        label="{{ $labelText }}"
        :readonly="$readonly"
        :rules="$rules"
        :autofocus="$resolvedFocus"
        :autocomplete="$resolvedAutoComplete ? 'on' : 'off'"
        {{-- Geef alle extra attributen (zoals v-model, , etc.) door aan de onderliggende control --}}
        {{ $attributes->except(['label', 'name', 'value', 'readonly', 'type', 'class', 'rules', 'labelClass', 'autocomplete']) }}
    >
        {{-- Doorlaat slot zodat select/textarea/options gebruikt kunnen worden --}}
        {{ $slot }}
    </x-admin::form.control-group.control>

    @if (! $isGroupedControl && $labelText && ! $useStaticLabel)
        <x-admin::form.control-group.label
            :switch="$type === 'switch'"
            :check="$type === 'checkbox'"
            class="{{ $computedLabelClass }}"
        >
            {{ $labelText }}
        </x-admin::form.control-group.label>
    @endif

    <x-admin::form.control-group.error control-name="{{ $controlName }}" />
</x-admin::form.control-group>
