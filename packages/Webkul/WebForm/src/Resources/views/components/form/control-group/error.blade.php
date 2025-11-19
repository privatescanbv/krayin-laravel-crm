@props([
    'name'        => null,
    'controlName' => '',
])

<v-error-message
    {{ $attributes }}
    name="{{ $name ?? $controlName }}"
    v-slot="{ message }"
>
    <p
        {{ $attributes->merge(['class' => 'mt-1 text-xs italic text-error']) }}
        v-text="message"
    >
    </p>
</v-error-message>
