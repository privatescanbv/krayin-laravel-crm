@props([
    /**
     * PHP mode: actual gender value (e.g. $entity->gender)
     */
    'gender' => null,

    /**
     * Vue mode: JS expression string (e.g. "element.gender")
     */
    'genderExpr' => null,

    /**
     * Force mode ("php" | "vue"). If omitted: inferred from genderExpr.
     */
    'mode' => null,

    /**
     * Tailwind classes for the <svg>.
     */
    'sizeClass' => 'w-3 h-3',

    /**
     * Tooltip prefix.
     */
    'titlePrefix' => 'Gender: ',
])

@php
    $resolvedMode = $mode ?: ($genderExpr ? 'vue' : 'php');
@endphp

@if ($resolvedMode === 'vue')
    @php
        // In Vue mode we expect a JS expression string like "element.gender".
        $expr = $genderExpr ?: 'gender';
    @endphp

    <span
        v-if="{{ $expr }}"
        {{ $attributes->merge(['class' => 'flex-shrink-0 text-gray-400 dark:text-gray-300']) }}
        :title="'{{ $titlePrefix }}' + ({{ $expr }})"
        aria-hidden="true"
    >
        <!-- Man -->
        <svg
            v-if="{{ $expr }} === 'Man'"
            xmlns="http://www.w3.org/2000/svg"
            class="{{ $sizeClass }}"
            viewBox="0 0 24 24"
            fill="currentColor"
        >
            <path d="M16 2h6v6h-2V5.41l-5.29 5.3a7 7 0 11-1.42-1.42L18.59 4H16V2z" />
        </svg>

        <!-- Vrouw -->
        <svg
            v-else-if="{{ $expr }} === 'Vrouw'"
            xmlns="http://www.w3.org/2000/svg"
            class="{{ $sizeClass }}"
            viewBox="0 0 24 24"
            fill="currentColor"
        >
            <path d="M12 2a7 7 0 00-1 13.93V18H9v2h2v2h2v-2h2v-2h-2v-2.07A7 7 0 0012 2z" />
        </svg>

        <!-- Onbekend -->
{{--        <svg--}}
{{--            v-else--}}
{{--            xmlns="http://www.w3.org/2000/svg"--}}
{{--            class="{{ $sizeClass }} opacity-70"--}}
{{--            viewBox="0 0 24 24"--}}
{{--            fill="currentColor"--}}
{{--        >--}}
{{--            <path d="M12 2a7 7 0 00-4 12.74V17h2v-3h2v3h2v-3a7 7 0 00-2-12zM11 20h2v2h-2z" />--}}
{{--        </svg>--}}
    </span>
@else
    @php
        if($gender instanceof BackedEnum) {
            $value =$gender->value;
        } else {
            $value = $gender;
        }
    @endphp

    @if (!empty($value))
        <span
            {{ $attributes->merge(['class' => 'flex-shrink-0 text-gray-400 dark:text-gray-300']) }}
            title="{{ $titlePrefix }}{{ $value }}"
            aria-hidden="true"
        >
            @if ($value === 'Man')
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="{{ $sizeClass }} opacity-70"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path d="M16 2h6v6h-2V5.41l-5.29 5.3a7 7 0 11-1.42-1.42L18.59 4H16V2z" />
                </svg>
            @elseif ($value === 'Vrouw')
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="{{ $sizeClass }} opacity-70"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path d="M12 2a7 7 0 00-1 13.93V18H9v2h2v2h2v-2h2v-2h-2v-2.07A7 7 0 0012 2z" />
                </svg>
{{--            @else--}}
{{--                <svg--}}
{{--                    xmlns="http://www.w3.org/2000/svg"--}}
{{--                    class="{{ $sizeClass }} opacity-70"--}}
{{--                    viewBox="0 0 24 24"--}}
{{--                    fill="currentColor"--}}
{{--                >--}}
{{--                    <path d="M12 2a7 7 0 00-4 12.74V17h2v-3h2v3h2v-3a7 7 0 00-2-12zM11 20h2v2h-2z" />--}}
{{--                </svg>--}}
            @endif
        </span>
    @endif
@endif

