@props([
    'name',
    'label',
    'value' => 0,
    'rules' => '',
    'commentField' => null,
    'commentOnNo' => false,
])

@php
    $hasRequiredRule = \Illuminate\Support\Str::contains($rules, 'required');
@endphp

<div class="mb-4">
    <label class="block mb-2 text-sm font-medium {{ $hasRequiredRule ? 'required' : '' }}">
        {{ $label }}
    </label>

    <div class="flex gap-6">
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="radio"
                name="{{ $name }}"
                value="1"
                class="form-radio"
                {{ (int) $value === 1 ? 'checked' : '' }}
                @if ($commentField)
                    onchange="toggleCommentField('{{ $commentField }}', {{ $commentOnNo ? 'false' : 'true' }})"
                    @if ($commentOnNo) data-comment-inverted="1" @endif
                @endif
            >
            Ja
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="radio"
                name="{{ $name }}"
                value="0"
                class="form-radio"
                {{ (int) $value === 0 ? 'checked' : '' }}
                @if ($commentField)
                    onchange="toggleCommentField('{{ $commentField }}', {{ $commentOnNo ? 'true' : 'false' }})"
                    @if ($commentOnNo) data-comment-inverted="1" @endif
                @endif
            >
            Nee
        </label>
    </div>

    {{-- error rendering --}}
    @error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
