@props(['check' => false, 'static' => false, 'switch' => false])

@php
    // Floating (Standaard voor tekstvelden)
    $floatingClasses = 'absolute left-0 top-4 ml-2 z-10 -translate-y-6 max-w-[80%] overflow-auto text-ellipsis text-xs pointer-events-none bg-gradient-to-t from-neutral-bg to-white px-1 duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500 peer-placeholder-shown:bg-none';

    // Checkbox/Radio (Naast de input)
    $checkClasses = 'ml-2 text-sm font-medium text-gray-700 select-none cursor-pointer';

    // Static (Gewoon een titel boven een groep)
    $staticClasses = 'block text-sm font-medium text-gray-700 mb-2';

    if ($static) {
        $classes = $staticClasses;
    } elseif ($check || $switch) {
        $classes = $checkClasses;
    } else {
        $classes = $floatingClasses;
    }
@endphp

<label {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</label>
